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
                                <button type="button" class="btn btn-sm" onclick="applySubject('${suggestion.subject.replace(/'/g, "\\'")}')">
                                    Use This Subject
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
            const contentEditor = document.getElementById('body');
            const content = contentEditor.value;
            
            if (!content) {
                alert('Please enter some content first');
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
                                <pre style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin-top: 5px;">${data.improved_content}</pre>
                            </div>
                        </div>
                        <div class="suggestion-actions">
                            <button type="button" class="btn btn-sm" onclick="applyContent(\`${data.improved_content.replace(/`/g, '\\`')}\`)">
                                Use This Content
                            </button>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                resultsDiv.innerHTML = `<div class="notification error">Error: ${error.message}</div>`;
            });
        }
        
        function analyzeSpamScore() {
            const subject = document.getElementById('subject').value;
            const content = document.getElementById('body').value;
            
            if (!subject || !content) {
                alert('Please enter both subject and content first');
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
                body: 'action=analyze_spam&subject=' + encodeURIComponent(subject) + '&content=' + encodeURIComponent(content)
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    resultsDiv.innerHTML = `<div class="notification error">${data.error}</div>`;
                    return;
                }
                
                let scoreColor = data.score < 3 ? 'green' : (data.score < 7 ? 'orange' : 'red');
                
                resultsDiv.innerHTML = `
                    <div class="ai-suggestion">
                        <div class="suggestion-header">
                            <span class="suggestion-title">Spam Analysis</span>
                            <span class="suggestion-score" style="background-color: ${scoreColor};">${data.score}/10</span>
                        </div>
                        <div class="suggestion-content">
                            <p>${data.analysis}</p>
                            <ul style="margin-top: 10px;">
                                ${data.issues.map(issue => `<li>${issue}</li>`).join('')}
                            </ul>
                            <div style="margin-top: 15px;">
                                <strong>Recommendations:</strong>
                                <ul>
                                    ${data.recommendations.map(rec => `<li>${rec}</li>`).join('')}
                                </ul>
                            </div>
                        </div>
                    </div>
                `;
            })
            .catch(error => {
                resultsDiv.innerHTML = `<div class="notification error">Error: ${error.message}</div>`;
            });
        }
        
        function applySubject(subject) {
            document.getElementById('subject').value = subject;
            document.getElementById('ai-assistant-results').style.display = 'none';
        }
        
        function applyContent(content) {
            document.getElementById('body').value = content;
            document.getElementById('ai-assistant-results').style.display = 'none';
        }
    </script>
HTML;
}

/**
 * Generate an AI-powered subject line based on content
 * @param string $content The newsletter content
 * @return string|null The generated subject line, or null if generation failed
 */
function generateAISubjectLine($content) {
    global $db;
    
    if (!isAIAssistantEnabled()) {
        return null;
    }
    
    // In a real implementation, this would call an AI API
    // For demonstration, we'll return a basic subject line
    return "Newsletter: " . date("F Y") . " Updates";
}