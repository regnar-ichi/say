<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

class Fb2Parser
{
    private const MIN_PAGE_LENGTH = 4000;
    private const TARGET_PAGE_LENGTH = 5000;
    private const MAX_PAGE_LENGTH = 6000;
    private const MAX_COVER_SIZE = 5242880;

    public function parse(string $xmlContent, string $fallbackTitle): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadXML($xmlContent, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            throw new RuntimeException('Could not parse FB2 file.');
        }

        $xpath = new DOMXPath($document);
        $title = $this->extractBookTitle($xpath, $fallbackTitle);
        $paragraphs = $this->extractParagraphs($xpath);

        if (empty($paragraphs)) {
            throw new RuntimeException('FB2 file does not contain readable text.');
        }

        return [
            'title' => $title,
            'pages' => $this->buildPages($paragraphs, $title),
            'cover' => $this->extractCover($xpath),
            'errors_count' => count($errors),
        ];
    }

    private function extractBookTitle(DOMXPath $xpath, string $fallbackTitle): string
    {
        $nodes = $xpath->query('//*[local-name()="description"]/*[local-name()="title-info"]/*[local-name()="book-title"]');
        $title = $nodes && $nodes->length > 0 ? $this->normalizeText($nodes->item(0)->textContent) : '';

        return $title !== '' ? $title : $fallbackTitle;
    }

    private function extractParagraphs(DOMXPath $xpath): array
    {
        $bodies = $xpath->query('/*[local-name()="FictionBook"]/*[local-name()="body"]');

        if (!$bodies || $bodies->length === 0) {
            return [];
        }

        $paragraphs = [];

        foreach ($bodies as $body) {
            if (!$body instanceof DOMElement) {
                continue;
            }

            if (strtolower($body->getAttribute('name')) === 'notes') {
                continue;
            }

            $this->collectTextBlocks($body, $paragraphs);
        }

        return $paragraphs;
    }

    private function extractCover(DOMXPath $xpath): ?array
    {
        $imageNodes = $xpath->query('//*[local-name()="description"]/*[local-name()="title-info"]/*[local-name()="coverpage"]/*[local-name()="image"]');

        if (!$imageNodes || $imageNodes->length === 0) {
            return null;
        }

        $imageNode = $imageNodes->item(0);

        if (!$imageNode instanceof DOMElement) {
            return null;
        }

        $binaryId = $this->extractImageHref($imageNode);

        if ($binaryId === '') {
            return null;
        }

        $binaryNodes = $xpath->query('//*[local-name()="binary"]');

        if (!$binaryNodes || $binaryNodes->length === 0) {
            return null;
        }

        foreach ($binaryNodes as $binaryNode) {
            if (!$binaryNode instanceof DOMElement || $binaryNode->getAttribute('id') !== $binaryId) {
                continue;
            }

            $extension = $this->extensionForContentType($binaryNode->getAttribute('content-type'));

            if ($extension === null) {
                return null;
            }

            $encoded = preg_replace('/\s+/', '', $binaryNode->textContent);

            if (!is_string($encoded)) {
                return null;
            }

            $content = base64_decode($encoded, true);

            if ($content === false || strlen($content) === 0 || strlen($content) > self::MAX_COVER_SIZE) {
                return null;
            }

            return [
                'content' => $content,
                'extension' => $extension,
            ];
        }

        return null;
    }

    private function extractImageHref(DOMElement $imageNode): string
    {
        $href = $imageNode->getAttribute('href');

        if ($href === '') {
            $href = $imageNode->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
        }

        if ($href === '') {
            $href = $imageNode->getAttribute('xlink:href');
        }

        if ($href === '') {
            $href = $imageNode->getAttribute('l:href');
        }

        $href = trim($href);

        if (str_starts_with($href, '#')) {
            $href = substr($href, 1);
        }

        return rawurldecode($href);
    }

    private function extensionForContentType(string $contentType): ?string
    {
        $contentType = strtolower(trim(explode(';', $contentType)[0] ?? ''));

        return match ($contentType) {
            'image/jpeg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
    }

    private function collectTextBlocks(DOMNode $node, array &$paragraphs): void
    {
        if ($node instanceof DOMElement && in_array($node->localName, ['p', 'subtitle', 'text-author'], true)) {
            $text = $this->normalizeText($node->textContent);

            if ($text !== '') {
                $paragraphs[] = $text;
            }

            return;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && in_array($child->localName, ['binary', 'image'], true)) {
                continue;
            }

            $this->collectTextBlocks($child, $paragraphs);
        }
    }

    private function buildPages(array $paragraphs, string $bookTitle): array
    {
        $pages = [];
        $current = [];
        $currentLength = 0;

        foreach ($paragraphs as $paragraph) {
            $paragraphLength = mb_strlen($paragraph);

            if ($paragraphLength > self::MAX_PAGE_LENGTH) {
                if (!empty($current)) {
                    $pages[] = $this->makePage($current, $bookTitle);
                    $current = [];
                    $currentLength = 0;
                }

                foreach ($this->splitLongParagraph($paragraph) as $part) {
                    $pages[] = $this->makePage([$part], $bookTitle);
                }

                continue;
            }

            $nextLength = $currentLength + $paragraphLength + (!empty($current) ? 2 : 0);

            if (!empty($current) && $nextLength > self::MAX_PAGE_LENGTH && $currentLength >= self::MIN_PAGE_LENGTH) {
                $pages[] = $this->makePage($current, $bookTitle);
                $current = [];
                $currentLength = 0;
            }

            $current[] = $paragraph;
            $currentLength += $paragraphLength + ($currentLength > 0 ? 2 : 0);

            if ($currentLength >= self::TARGET_PAGE_LENGTH) {
                $pages[] = $this->makePage($current, $bookTitle);
                $current = [];
                $currentLength = 0;
            }
        }

        if (!empty($current)) {
            $pages[] = $this->makePage($current, $bookTitle);
        }

        return $pages;
    }

    private function splitLongParagraph(string $paragraph): array
    {
        $words = preg_split('/\s+/u', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
        $parts = [];
        $current = '';

        foreach ($words as $word) {
            $next = $current === '' ? $word : $current . ' ' . $word;

            if ($current !== '' && mb_strlen($next) > self::MAX_PAGE_LENGTH) {
                $parts[] = $current;
                $current = $word;
                continue;
            }

            $current = $next;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    private function makePage(array $paragraphs, string $title): array
    {
        return [
            'title' => $title,
            'content' => implode("\n\n", $paragraphs),
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace('/[ \t\r\n]+/u', ' ', $text);

        return trim($text);
    }
}
