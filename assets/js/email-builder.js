/**
 * Email Builder JavaScript
 * This file handles the drag-and-drop functionality and interactive aspects
 * of the email template builder
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the builder
    const emailEditor = document.getElementById('email-editor');
    const templateContent = document.getElementById('template_content');
    
    // Parse the existing template content
    if (templateContent.value) {
        emailEditor.innerHTML = extractBodyContent(templateContent.value);
    }
    
    // Initialize component tabs
    const tabButtons = document.querySelectorAll('.tab-btn');
    const componentLists = document.querySelectorAll('.category-components');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove active class from all buttons
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            // Add active class to clicked button
            button.classList.add('active');
            
            // Hide all component lists
            componentLists.forEach(list => list.style.display = 'none');
            
            // Show the selected component list
            const category = button.getAttribute('data-category');
            document.querySelector(`.category-components[data-category="${category}"]`).style.display = 'block';
        });
    });
    
    // Make components draggable
    initDraggableComponents();
    
    // Initialize layout components
    initLayoutComponents();
    
    // Initialize the preview modal
    const previewBtn = document.getElementById('preview-btn');
    const previewModal = document.getElementById('preview-modal');
    const previewFrame = document.getElementById('preview-frame');
    const closeBtns = document.querySelectorAll('.close');
    
    previewBtn.addEventListener('click', function() {
        // Update the hidden input with current editor content
        updateTemplateContent();
        
        // Create complete HTML document for preview
        const completeHtml = createCompleteHtml();
        
        // Display in preview iframe
        const frameDoc = previewFrame.contentDocument || previewFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(completeHtml);
        frameDoc.close();
        
        // Show the modal
        previewModal.style.display = 'block';
    });
    
    // Initialize the mobile preview modal
    const responsivePreviewBtn = document.getElementById('responsive-preview-btn');
    const mobilePreviewModal = document.getElementById('mobile-preview-modal');
    const mobilePreviewFrame = document.getElementById('mobile-preview-frame');
    
    responsivePreviewBtn.addEventListener('click', function() {
        // Update the hidden input with current editor content
        updateTemplateContent();
        
        // Create complete HTML document for preview
        const completeHtml = createCompleteHtml();
        
        // Display in mobile preview iframe
        const frameDoc = mobilePreviewFrame.contentDocument || mobilePreviewFrame.contentWindow.document;
        frameDoc.open();
        frameDoc.write(completeHtml);
        frameDoc.close();
        
        // Show the modal
        mobilePreviewModal.style.display = 'block';
    });
    
    // Close modal when clicking the × button
    closeBtns.forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            previewModal.style.display = 'none';
            mobilePreviewModal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside the content
    window.addEventListener('click', function(event) {
        if (event.target === previewModal) {
            previewModal.style.display = 'none';
        }
        if (event.target === mobilePreviewModal) {
            mobilePreviewModal.style.display = 'none';
        }
    });
    
    // Save the template when form is submitted
    const templateForm = document.getElementById('template-form');
    templateForm.addEventListener('submit', function(event) {
        // Update the hidden input with current editor content
        updateTemplateContent();
    });
    
    // Initialize delete button
    const deleteBtn = document.getElementById('delete-btn');
    deleteBtn.addEventListener('click', function() {
        const selectedElement = document.querySelector('.content-block.active');
        if (selectedElement) {
            selectedElement.remove();
            deleteBtn.disabled = true;
            showElementProperties(null);
        }
    });
    
    // Function to update the hidden input with the current editor content
    function updateTemplateContent() {
        const editorContent = emailEditor.innerHTML;
        templateContent.value = createCompleteHtml(editorContent);
    }
    
    // Function to create a complete HTML document from the editor content
    function createCompleteHtml(content = null) {
        if (content === null) {
            content = emailEditor.innerHTML;
        }
        
        return `<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Template</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
        }
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        ${content}
    </div>
</body>
</html>`;
    }
    
    // Function to extract body content from a complete HTML document
    function extractBodyContent(html) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const container = doc.querySelector('.email-container');
        
        return container ? container.innerHTML : '';
    }
    
    // Initialize draggable components
    function initDraggableComponents() {
        const componentItems = document.querySelectorAll('.component-item:not(.layout-item)');
        
        componentItems.forEach(item => {
            item.draggable = true;
            
            item.addEventListener('dragstart', function(e) {
                e.dataTransfer.setData('text/plain', JSON.stringify(item.dataset.component));
                e.dataTransfer.effectAllowed = 'copy';
            });
        });
        
        // Enable drop zones within the editor
        emailEditor.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            
            // Highlight potential drop area
            const dropTarget = findDropTarget(e.target);
            if (dropTarget) {
                dropTarget.classList.add('highlight-drop-area');
            }
        });
        
        emailEditor.addEventListener('dragleave', function(e) {
            // Remove highlight from drop areas
            const dropAreas = document.querySelectorAll('.highlight-drop-area');
            dropAreas.forEach(area => area.classList.remove('highlight-drop-area'));
        });
        
        emailEditor.addEventListener('drop', function(e) {
            e.preventDefault();
            
            // Remove any existing highlight
            const dropAreas = document.querySelectorAll('.highlight-drop-area');
            dropAreas.forEach(area => area.classList.remove('highlight-drop-area'));
            
            // Find valid drop target (content-block)
            const dropTarget = findDropTarget(e.target);
            if (!dropTarget) return;
            
            // Get component data
            try {
                const componentData = JSON.parse(e.dataTransfer.getData('text/plain'));
                const componentHtml = componentData.html_content;
                
                // Insert the component into the drop target
                dropTarget.insertAdjacentHTML('beforeend', componentHtml);
                
                // Make the new element selectable
                const addedElement = dropTarget.lastElementChild;
                makeElementSelectable(addedElement);
            } catch (error) {
                console.error('Error adding component:', error);
            }
        });
    }
    
    // Initialize layout components
    function initLayoutComponents() {
        const layoutItems = document.querySelectorAll('.layout-item');
        
        layoutItems.forEach(item => {
            item.addEventListener('click', function() {
                // Get layout data
                const layoutData = JSON.parse(item.dataset.layout);
                const layoutHtml = layoutData.html_structure;
                
                // Ask for confirmation if editor has content
                if (emailEditor.innerHTML.trim() !== '' && !confirm('Applying a new layout will replace your current content. Continue?')) {
                    return;
                }
                
                // Set the layout in the editor
                emailEditor.innerHTML = layoutHtml;
                
                // Initialize the content blocks to be droppable
                const contentBlocks = emailEditor.querySelectorAll('.content-block');
                contentBlocks.forEach(block => {
                    block.addEventListener('click', function(e) {
                        e.stopPropagation();
                        selectElement(block);
                    });
                });
            });
        });
    }
    
    // Find a valid drop target (content-block element)
    function findDropTarget(element) {
        if (!element) return null;
        
        // If this is a content-block, return it
        if (element.classList && element.classList.contains('content-block')) {
            return element;
        }
        
        // Check if element is inside a content-block
        let parent = element.parentElement;
        while (parent) {
            if (parent === emailEditor) {
                // If we've reached the editor without finding a content-block,
                // then the element is not in a valid drop zone
                return null;
            }
            
            if (parent.classList && parent.classList.contains('content-block')) {
                return parent;
            }
            
            parent = parent.parentElement;
        }
        
        return null;
    }
    
    // Make an element selectable
    function makeElementSelectable(element) {
        if (!element) return;
        
        element.addEventListener('click', function(e) {
            e.stopPropagation();
            selectElement(element);
        });
        
        // Make any child elements selectable too
        const childElements = element.querySelectorAll('*');
        childElements.forEach(child => {
            child.addEventListener('click', function(e) {
                e.stopPropagation();
                selectElement(child);
            });
        });
    }
    
    // Select an element and show its properties
    function selectElement(element) {
        // Deselect previously selected element
        const previouslySelected = document.querySelector('.content-block.active');
        if (previouslySelected) {
            previouslySelected.classList.remove('active');
        }
        
        // Select the new element
        element.classList.add('active');
        
        // Enable delete button
        deleteBtn.disabled = false;
        
        // Show element properties
        showElementProperties(element);
    }
    
    // Show properties for the selected element
    function showElementProperties(element) {
        const propertiesPanel = document.getElementById('element-properties');
        
        if (!element) {
            // No element selected, show default message
            propertiesPanel.innerHTML = '<p class="no-selection-message">Select an element to edit its properties</p>';
            return;
        }
        
        // Generate properties based on element type
        let propertiesHtml = '';
        
        if (element.nodeName === 'DIV') {
            // Container properties
            propertiesHtml += `
                <div class="property-group">
                    <label for="bg-color">Background Color</label>
                    <input type="color" id="bg-color" value="${rgbToHex(element.style.backgroundColor || '#ffffff')}" 
                        onchange="document.querySelector('.content-block.active').style.backgroundColor = this.value">
                </div>
                <div class="property-group">
                    <label for="padding">Padding (px)</label>
                    <input type="number" id="padding" min="0" max="100" value="${parseInt(element.style.padding) || 0}"
                        onchange="document.querySelector('.content-block.active').style.padding = this.value + 'px'">
                </div>
            `;
        } else if (element.nodeName === 'IMG') {
            // Image properties
            propertiesHtml += `
                <div class="property-group">
                    <label for="img-src">Image Source</label>
                    <input type="text" id="img-src" value="${element.src}"
                        onchange="document.querySelector('.content-block.active').src = this.value">
                    <button class="btn btn-sm" onclick="openMediaLibrary()">Choose from Media Library</button>
                </div>
                <div class="property-group">
                    <label for="img-alt">Alt Text</label>
                    <input type="text" id="img-alt" value="${element.alt || ''}"
                        onchange="document.querySelector('.content-block.active').alt = this.value">
                </div>
            `;
        } else if (element.nodeName === 'P' || element.nodeName === 'H1' || 
                  element.nodeName === 'H2' || element.nodeName === 'H3') {
            // Text properties
            propertiesHtml += `
                <div class="property-group">
                    <label for="text-content">Text</label>
                    <textarea id="text-content" rows="4"
                        onchange="document.querySelector('.content-block.active').innerText = this.value">${element.innerText}</textarea>
                </div>
                <div class="property-group">
                    <label for="text-color">Text Color</label>
                    <input type="color" id="text-color" value="${rgbToHex(element.style.color || '#000000')}"
                        onchange="document.querySelector('.content-block.active').style.color = this.value">
                </div>
                <div class="property-group">
                    <label for="font-size">Font Size (px)</label>
                    <input type="number" id="font-size" min="10" max="72" value="${parseInt(element.style.fontSize) || 16}"
                        onchange="document.querySelector('.content-block.active').style.fontSize = this.value + 'px'">
                </div>
            `;
        } else if (element.nodeName === 'A') {
            // Link properties
            propertiesHtml += `
                <div class="property-group">
                    <label for="link-text">Link Text</label>
                    <input type="text" id="link-text" value="${element.innerText}"
                        onchange="document.querySelector('.content-block.active').innerText = this.value">
                </div>
                <div class="property-group">
                    <label for="link-href">Link URL</label>
                    <input type="text" id="link-href" value="${element.href}"
                        onchange="document.querySelector('.content-block.active').href = this.value">
                </div>
                <div class="property-group">
                    <label for="link-color">Link Color</label>
                    <input type="color" id="link-color" value="${rgbToHex(element.style.color || '#0066cc')}"
                        onchange="document.querySelector('.content-block.active').style.color = this.value">
                </div>
            `;
        }
        
        // Common properties for all elements
        propertiesHtml += `
            <div class="property-group">
                <label for="text-align">Text Alignment</label>
                <select id="text-align" 
                    onchange="document.querySelector('.content-block.active').style.textAlign = this.value">
                    <option value="left" ${element.style.textAlign === 'left' ? 'selected' : ''}>Left</option>
                    <option value="center" ${element.style.textAlign === 'center' ? 'selected' : ''}>Center</option>
                    <option value="right" ${element.style.textAlign === 'right' ? 'selected' : ''}>Right</option>
                </select>
            </div>
        `;
        
        propertiesPanel.innerHTML = propertiesHtml;
    }
    
    // Function to convert RGB to Hex (used in element properties panel)
    function rgbToHex(rgb) {
        // If rgb is already a hex color, just return it
        if (rgb.startsWith('#')) return rgb;
        
        // Extract RGB values
        let rgbArr = rgb.match(/\d+/g);
        if (!rgbArr || rgbArr.length < 3) return '#ffffff';
        
        // Convert to hex
        return '#' + ((1 << 24) + (parseInt(rgbArr[0]) << 16) + (parseInt(rgbArr[1]) << 8) + parseInt(rgbArr[2])).toString(16).slice(1);
    }
    
    // Function to open the media library
    function openMediaLibrary() {
        // Open media library in new window
        window.open('media_library.php?select=1', 'mediaLibrary', 'width=800,height=600');
        
        // Handle selected image from media library
        window.addEventListener('message', function(event) {
            if (event.data.type === 'media-selected') {
                const selectedImg = document.querySelector('.content-block.active');
                if (selectedImg && selectedImg.nodeName === 'IMG') {
                    selectedImg.src = event.data.url;
                    document.getElementById('img-src').value = event.data.url;
                }
            }
        });
    }
});