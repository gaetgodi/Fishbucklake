/**
 * Contact Form JavaScript
 * Handles form submission, validation, and spam protection
 * Site: fishbucklake.com (staging)
 */

(function() {
    'use strict';
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        
        const form = document.getElementById('fbl-contact-form');
        const submitButton = document.getElementById('contact-submit');
        const responseDiv = document.getElementById('form-response');
        const formTimeInput = document.getElementById('form-time');
        
        if (!form) {
            console.error('Contact form not found');
            return;
        }
        
        // Set form load time (for spam detection)
        formTimeInput.value = Math.floor(Date.now() / 1000);
        
        // Form submission handler
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Disable form during submission
            setLoadingState(true);
            hideResponse();
            
            // Collect form data
            const formData = {
                name: document.getElementById('contact-name').value.trim(),
                email: document.getElementById('contact-email').value.trim(),
                phone: document.getElementById('contact-phone').value.trim(),
                category: document.getElementById('contact-category').value,
                message: document.getElementById('contact-message').value.trim(),
                website: document.getElementById('contact-website').value, // Honeypot
                form_time: formTimeInput.value // Timing check
            };
            
            // Client-side validation
            const validationError = validateForm(formData);
            if (validationError) {
                showResponse(validationError, 'error');
                setLoadingState(false);
                return;
            }
            
            // Submit to WordPress REST API
            try {
                const response = await fetch('/wp-json/fbl/v1/contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Success!
                    showResponse(data.message, 'success');
                    form.reset();
                    
                    // Reset form time for next submission
                    formTimeInput.value = Math.floor(Date.now() / 1000);
                    
                    // Scroll to response
                    responseDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    
                } else {
                    // Server error
                    const errorMessage = data.message || 'An error occurred. Please try again.';
                    showResponse(errorMessage, 'error');
                }
                
            } catch (error) {
                console.error('Form submission error:', error);
                showResponse('Network error. Please check your connection and try again.', 'error');
            }
            
            setLoadingState(false);
        });
        
        /**
         * Validate form data
         */
        function validateForm(data) {
            // Check required fields
            if (!data.name) {
                return 'Please enter your name.';
            }
            
            if (!data.email) {
                return 'Please enter your email address.';
            }
            
            if (!data.category) {
                return 'Please select a request type.';
            }
            
            if (!data.message) {
                return 'Please enter your message.';
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(data.email)) {
                return 'Please enter a valid email address.';
            }
            
            // Check message length
            if (data.message.length < 10) {
                return 'Please provide a more detailed message (at least 10 characters).';
            }
            
            if (data.message.length > 5000) {
                return 'Message is too long (maximum 5000 characters).';
            }
            
            // Spam check: Honeypot field should be empty
            if (data.website && data.website.length > 0) {
                return 'Spam detected. Please try again.';
            }
            
            return null; // No errors
        }
        
        /**
         * Set loading state
         */
        function setLoadingState(isLoading) {
            if (isLoading) {
                form.classList.add('submitting');
                submitButton.classList.add('loading');
                submitButton.disabled = true;
            } else {
                form.classList.remove('submitting');
                submitButton.classList.remove('loading');
                submitButton.disabled = false;
            }
        }
        
        /**
         * Show response message
         */
        function showResponse(message, type = 'success') {
            responseDiv.textContent = message;
            responseDiv.className = 'form-response ' + type;
            responseDiv.style.display = 'block';
            
            // Auto-hide success messages after 10 seconds
            if (type === 'success') {
                setTimeout(function() {
                    hideResponse();
                }, 10000);
            }
        }
        
        /**
         * Hide response message
         */
        function hideResponse() {
            responseDiv.style.display = 'none';
            responseDiv.textContent = '';
            responseDiv.className = 'form-response';
        }
        
        /**
         * Real-time validation feedback
         */
        
        // Email validation
        const emailInput = document.getElementById('contact-email');
        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (this.value && !emailRegex.test(this.value)) {
                this.setCustomValidity('Please enter a valid email address');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Phone formatting (optional)
        const phoneInput = document.getElementById('contact-phone');
        phoneInput.addEventListener('input', function() {
            // Remove non-numeric characters
            let value = this.value.replace(/\D/g, '');
            
            // Format as (XXX) XXX-XXXX
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = '(' + value;
                } else if (value.length <= 6) {
                    value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
                } else {
                    value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6, 10);
                }
            }
            
            this.value = value;
        });
        
        /**
         * Character counter for message (optional)
         */
        const messageInput = document.getElementById('contact-message');
        const messageLabel = messageInput.previousElementSibling;
        
        messageInput.addEventListener('input', function() {
            const length = this.value.length;
            const maxLength = 5000;
            
            // Update label with character count
            const counter = messageLabel.querySelector('.char-count');
            if (counter) {
                counter.textContent = length + '/' + maxLength;
            } else if (length > 0) {
                const span = document.createElement('span');
                span.className = 'char-count';
                span.style.color = 'var(--fbl-light)';
                span.style.opacity = '0.6';
                span.style.fontWeight = '400';
                span.style.float = 'right';
                span.textContent = length + '/' + maxLength;
                messageLabel.appendChild(span);
            }
        });
        
        // Log successful initialization
        console.log('FBL Contact Form initialized');
    });
    
})();

/**
 * SPAM PROTECTION FEATURES:
 * 
 * 1. Honeypot Field
 *    - Hidden "website" field that bots fill in
 *    - Humans can't see it, bots always fill it
 *    - If filled = instant spam
 * 
 * 2. Timing Check
 *    - Records when form loads
 *    - Checks how long user took to fill it
 *    - Too fast (< 3 seconds) = likely bot
 * 
 * 3. Server-Side Checks
 *    - Keyword detection (viagra, casino, etc.)
 *    - Link counting (too many links = spam)
 *    - Suspicious email domains
 *    - All handled in fbl-contact-system.php
 * 
 * 4. Client Validation
 *    - Email format check
 *    - Message length check
 *    - Required field validation
 */