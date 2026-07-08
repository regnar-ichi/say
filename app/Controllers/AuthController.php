<?php

namespace App\Controllers;

use App\Core\Database;

class AuthController
{
    public function showLogin(): void
    {
        require __DIR__ . '/../Views/auth/login.php';
    }

    public function showRegister(): void
    {
        require __DIR__ . '/../Views/auth/register.php';
    }

    public function login(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $mysqli = Database::connect();

        $stmt = $mysqli->prepare("
            SELECT id, login, password, role_id, is_approved
            FROM users
            WHERE login = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $login);
        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Wrong login or password';
            require __DIR__ . '/../Views/auth/login.php';
            return;
        }

        if ((int)$user['is_approved'] !== 1) {
            $error = 'User is not approved';
            require __DIR__ . '/../Views/auth/login.php';
            return;
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_login'] = $user['login'];
        $_SESSION['role_id'] = (int)$user['role_id'];

        header('Location: /');
        exit;
    }

    public function register(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $passwordConfirm = trim($_POST['password_confirm'] ?? '');

        if ($login === '' || $password === '' || $passwordConfirm === '') {
            $error = 'Fill all fields';
            require __DIR__ . '/../Views/auth/register.php';
            return;
        }

        if (strlen($login) < 3) {
            $error = 'Login must be at least 3 characters';
            require __DIR__ . '/../Views/auth/register.php';
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $login)) {
            $error = 'Login can contain only letters, numbers, underscore and dash';
            require __DIR__ . '/../Views/auth/register.php';
            return;
        }

        if (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
            require __DIR__ . '/../Views/auth/register.php';
            return;
        }

        if ($password !== $passwordConfirm) {
            $error = 'Passwords do not match';
            require __DIR__ . '/../Views/auth/register.php';
            return;
        }

        $mysqli = Database::connect();

        $stmt = $mysqli->prepare("
            SELECT id
            FROM users
            WHERE login = ?
            LIMIT 1
        ");

        $stmt->bind_param('s', $login);
        $stmt->execute();

        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($exists) {
            $error = 'Login already exists';
            require __DIR__ . '/../Views/auth/register.php';
            return;
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $roleId = 2;
        $isApproved = 1;

        $stmt = $mysqli->prepare("
            INSERT INTO users (login, password, role_id, is_approved)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param('ssii', $login, $passwordHash, $roleId, $isApproved);
        $stmt->execute();

        $userId = $stmt->insert_id;
        $stmt->close();

        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['user_login'] = $login;
        $_SESSION['role_id'] = $roleId;

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();

        header('Location: /login');
        exit;
    }    
}
