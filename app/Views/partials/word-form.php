<div id="wordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add New Word</h3>
            <button class="modal-close" id="closeModal">&times;</button>
        </div>
        
        <form id="wordForm" class="modal-form">
            <div class="form-group">
                <input type="text" id="wordText" name="text" placeholder="English word" required>
            </div>
            
            <div class="form-group">
                <input type="text" id="wordTranslate" name="translate" placeholder="Russian translate" required>
            </div>

            <div class="form-group">
                <select id="wordType" name="type">
                    <option value="">Type (optional)</option>
                    <option value="noun">noun</option>
                    <option value="verb">verb</option>
                    <option value="adjective">adjective</option>
                    <option value="adverb">adverb</option>
                    <option value="phrase">phrase</option>
                    <option value="idiom">idiom</option>
                    <option value="proverb">proverb</option>
                    <option value="slang">slang</option>
                    <option value="joke">joke</option>
                </select>
            </div>

            <div class="form-group">
                <input type="text" id="wordExample" name="example" placeholder="Example (optional)">
            </div>

            <div class="form-group">
                <input type="text" id="wordExampleRu" name="example_ru" placeholder="Example in Russian (optional)">
            </div>
            
            <div class="modal-footer">
                <button type="button" id="cancelBtn" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary">Save word</button>
            </div>
        </form>
        
        <div id="formMessage" class="form-message" style="display: none;"></div>
    </div>
</div>
