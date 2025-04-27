/**
 * LumiNewsletter Embeddable Widget
 * Version: 1.0.0
 */
(function() {
    // Configuration
    const config = {
        targetSelector: '.lumi-newsletter-embed', // Default target element
        widgetUrl: 'https://yoursite.com/path-to-lumi/widget.php', // Update this URL
        height: '320px',
        width: '100%',
        maxWidth: '400px'
    };
    
    // Create the widget iframe when the document is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Find all target elements
        const targets = document.querySelectorAll(config.targetSelector);
        
        targets.forEach(function(target) {
            // Create iframe element
            const iframe = document.createElement('iframe');
            iframe.src = config.widgetUrl;
            iframe.style.width = target.getAttribute('data-width') || config.width;
            iframe.style.maxWidth = target.getAttribute('data-max-width') || config.maxWidth;
            iframe.style.height = target.getAttribute('data-height') || config.height;
            iframe.style.border = 'none';
            iframe.style.overflow = 'hidden';
            iframe.scrolling = 'no';
            iframe.frameBorder = '0';
            
            // Replace the target element with the iframe
            target.appendChild(iframe);
            
            // Add message listener for iframe resizing
            window.addEventListener('message', function(event) {
                // Verify the origin
                if (event.origin !== new URL(config.widgetUrl).origin) return;
                
                // Handle height adjustments
                if (event.data && event.data.type === 'resize') {
                    iframe.style.height = event.data.height + 'px';
                }
            });
        });
    });
    
    // Alternative method: Direct HTML embedding
    window.lumiNewsletterEmbed = function(targetId) {
        const target = document.getElementById(targetId);
        if (!target) return;
        
        // Load the widget content via AJAX
        const xhr = new XMLHttpRequest();
        xhr.open('GET', config.widgetUrl, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                target.innerHTML = xhr.responseText;
                
                // Set up form submission via AJAX
                const form = target.querySelector('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const formData = new FormData(form);
                        const xhr2 = new XMLHttpRequest();
                        xhr2.open('POST', config.widgetUrl, true);
                        xhr2.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                        xhr2.onreadystatechange = function() {
                            if (xhr2.readyState === 4 && xhr2.status === 200) {
                                try {
                                    const response = JSON.parse(xhr2.responseText);
                                    
                                    // Create or update message element
                                    let messageEl = target.querySelector('.lumi-message');
                                    if (!messageEl) {
                                        messageEl = document.createElement('div');
                                        messageEl.className = 'lumi-message';
                                        form.prepend(messageEl);
                                    }
                                    
                                    messageEl.textContent = response.message;
                                    messageEl.className = 'lumi-message ' + response.type;
                                    
                                    // Clear form on success
                                    if (response.type === 'success') {
                                        form.reset();
                                    }
                                } catch (error) {
                                    console.error('Error parsing response:', error);
                                }
                            }
                        };
                        xhr2.send(formData);
                    });
                }
            }
        };
        xhr.send();
    };
})();