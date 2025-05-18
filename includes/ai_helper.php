<?php
/**
 * AI Assistant Helper Functions
 * These functions help integrate AI suggestions into the newsletter composition
 */

/**
 * Check if AI Assistant is enabled
 * @return bool Whether AI Assistant is enabled
 */
function isAIAssistantEnabled() {
    global $db;
    
    $result = $db->query("SELECT enabled FROM features WHERE feature_name = 'ai_assistant'");
    if ($result && $result->num_rows > 0) {
        return (bool)$result->fetch_assoc()['enabled'];
    }
    
    return false; // Default to disabled if not found
}

/**
 * Get AI helper widget for newsletter composer
 * @return string HTML for AI helper widget
 */
function getAIHelperWidget() {
    if (!isAIAssistantEnabled()) {
        return '';
    }
    
    return <<<HTML
    <div class="ai-assistant-tools">
        <h4><i class="fas fa-robot"></i> AI Assistant</h4>
        <div class="ai-tools-buttons">
            <button type="button" class="btn btn-sm btn-outline" onclick="analyzeSubject()">
                <i class="fas fa-heading"></i> Optimize Subject
            </button>
            <button type="button" class="btn btn-sm btn-outline" onclick="improveContent()">
                <i class="fas fa-wand-magic-sparkles"></i> Enhance Content
            </button>
            <button type="button" class="btn btn-sm btn-outline" onclick="analyzeSpamScore()">
                <i class="fas fa-shield-alt"></i> Check Spam Score
            </button>
        </div>
        <div id="ai-assistant-results" style="display: none; margin-top: 15px;"></div>
    </div>

    <script>
        function analyzeSubject() {
            const subject = document.getElementById('subject').value;
            if (!subject) {
                alert('Please enter a subject line first');
                return;
            }
            
            // Show loading state
            const resultsDiv = document.getElementById('ai-assistant-results');
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Analyzing subject line...</div>';
            
            // Make AJAX request to the AI assistant
            fetch('ai_assistant_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=analyze_subject&subject=' + encodeURIComponent(subject)
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultsDiv.innerHTML = `<div class="notification error">${data.error}</div>`;
                    return;
                }
                
                let suggestionsHTML = '';
                data.suggestions.forEach(suggestion => {
                    suggestionsHTML += `
                        <div class="ai-suggestion">
                            <div class="suggestion-header">
                                <span class="suggestion-title">Suggested Subject</span>
                                <span class="suggestion-score">${Math.round(suggestion.predicted_open_rate * 100)}% Score</span>
                            </div>
                            <div class="suggestion-content">
                                <strong>${suggestion.subject}</strong>
                            </div>
                            <div class="suggestion-reason">
                                ${suggestion.reason}
                            </div>
                            <div class="suggestion-actions">
                                <button class="btn btn-sm btn-outline" onclick="copyToClipboard('${suggestion.subject.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                                <button class="btn btn-sm btn-primary" onclick="useSubject('${suggestion.subject.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-check"></i> Use This
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                resultsDiv.innerHTML = suggestionsHTML;
            })
            .catch(error => {
                resultsDiv.innerHTML = `<div class="notification error">Error: ${error.message}</div>`;
            });
        }
        
        function improveContent() {
            const content = tinymce.get('body').getContent();
            if (!content) {
                alert('Please add some content to enhance');
                return;
            }
            
            // Show loading state
            const resultsDiv = document.getElementById('ai-assistant-results');
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Analyzing content...</div>';
            
            // Make AJAX request to the AI assistant
            fetch('ai_assistant_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=improve_content&content=' + encodeURIComponent(content)
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultsDiv.innerHTML = `<div class="notification error">${data.error}</div>`;
                    return;
                }
                
                resultsDiv.innerHTML = `
                    <div class="ai-suggestion">
                        <div class="suggestion-header">
                            <span class="suggestion-title">Content Enhancement</span>
                        </div>
                        <div class="suggestion-content">
                            <p>${data.analysis}</p>
                            <div style="margin-top: 15px;">
                                <strong>Improved Content:</strong>
                                <div style="border: 1px solid #ddd; padding: 10px; margin-top: 5px; max-height: 200px; overflow-y: auto;">
                                    ${data.improved_content}
                                </div>
                            </div>
                        </div>
                        <div class="suggestion-actions" style="margin-top: 15px;">
                            <button class="btn btn-sm btn-outline" onclick="copyEnhancedContent()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                            <button class="btn btn-sm btn-primary" onclick="useEnhancedContent()">
                                <i class="fas fa-check"></i> Use Enhanced Content
                            </button>
                        </div>
                    </div>
                `;
                
                // Store the enhanced content for later use
                window.enhancedContent = data.improved_content;
            })
            .catch(error => {
                resultsDiv.innerHTML = `<div class="notification error">Error: ${error.message}</div>`;
            });
        }
        
        function analyzeSpamScore() {
            const content = tinymce.get('body').getContent();
            if (!content) {
                alert('Please add some content to analyze');
                return;
            }
            
            // Show loading state
            const resultsDiv = document.getElementById('ai-assistant-results');
            resultsDiv.style.display = 'block';
            resultsDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Checking spam score...</div>';
            
            // Make AJAX request to the AI assistant
            fetch('ai_assistant_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=analyze_spam&content=' + encodeURIComponent(content)
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultsDiv.innerHTML = `<div class="notification error">${data.error}</div>`;
                    return;
                }
                
                resultsDiv.innerHTML = `
                    <div class="ai-suggestion">
                        <div class="suggestion-header">
                            <span class="suggestion-title">Spam Score Analysis</span>
                            <span class="suggestion-score">${data.risk_level}</span>
                        </div>
                        <div class="suggestion-content">
                            <p>${data.summary}</p>
                            <div style="margin: 15px 0;">
                                <div style="background: linear-gradient(to right, green, yellow, red); height: 10px; border-radius: 5px; position: relative;">
                                    <div style="position: absolute; left: ${data.score}%; top: -6px; width: 2px; height: 20px; background: black;"></div>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                                    <span>Low Risk</span>
                                    <span>Medium</span>
                                    <span>High Risk</span>
                                </div>
                            </div>
                            <p><strong>Tips to improve:</strong></p>
                            <ul>
                                ${data.tips.map(tip => `<li>${tip}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                resultsDiv.innerHTML = `<div class="notification error">Error: ${error.message}</div>`;
            });
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            });
        }
        
        function useSubject(subject) {
            document.getElementById('subject').value = subject;
            document.getElementById('ai-assistant-results').style.display = 'none';
        }
        
        function copyEnhancedContent() {
            navigator.clipboard.writeText(window.enhancedContent).then(() => {
                alert('Enhanced content copied to clipboard!');
            });
        }
        
        function useEnhancedContent() {
            tinymce.get('body').setContent(window.enhancedContent);
            document.getElementById('ai-assistant-results').style.display = 'none';
        }
    </script>
HTML;
}