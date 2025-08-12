/**
 * TimCal Events Admin JavaScript
 *
 * Handles conditional field display and admin interface interactions
 * for the timcal_events post type.
 *
 * @package Timnashcouk\Timcal
 * @since 0.1.0
 */

(function() {
    'use strict';

    /**
     * Initialize the admin interface when DOM is ready.
     */
    function initEventsAdmin() {
        // Guard clause: Ensure we're on the right page.
        if (!document.getElementById('timcal_location_online')) {
            return;
        }

        initLocationTypeToggle();
        initFormValidation();
        initHostSelectEnhancement();
    }

    /**
     * Initialize location type toggle functionality.
     */
    function initLocationTypeToggle() {
        const onlineRadio = document.getElementById('timcal_location_online');
        const inPersonRadio = document.getElementById('timcal_location_in_person');
        const addressField = document.getElementById('timcal_address_field');

        if (!onlineRadio || !inPersonRadio || !addressField) {
            return;
        }

        /**
         * Toggle address field visibility based on location type.
         */
        function toggleAddressField() {
            if (inPersonRadio.checked) {
                addressField.style.display = 'block';
                addressField.classList.add('timcal-address-visible');
                
                // Focus on address field when it becomes visible
                const addressTextarea = document.getElementById('timcal_location_address');
                if (addressTextarea) {
                    setTimeout(() => addressTextarea.focus(), 100);
                }
            } else {
                addressField.style.display = 'none';
                addressField.classList.remove('timcal-address-visible');
                
                // Clear address field when hidden
                const addressTextarea = document.getElementById('timcal_location_address');
                if (addressTextarea) {
                    addressTextarea.value = '';
                }
            }
        }

        // Set initial state
        toggleAddressField();

        // Add event listeners
        onlineRadio.addEventListener('change', toggleAddressField);
        inPersonRadio.addEventListener('change', toggleAddressField);
    }

    /**
     * Initialize form validation.
     */
    function initFormValidation() {
        const form = document.getElementById('post');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function(event) {
            if (!validateEventForm()) {
                event.preventDefault();
                return false;
            }
        });
    }

    /**
     * Validate the event form before submission.
     *
     * @return {boolean} True if form is valid, false otherwise.
     */
    function validateEventForm() {
        let isValid = true;
        const errors = [];

        // Validate address for in-person events
        const inPersonRadio = document.getElementById('timcal_location_in_person');
        const addressTextarea = document.getElementById('timcal_location_address');

        if (inPersonRadio && inPersonRadio.checked && addressTextarea) {
            const address = addressTextarea.value.trim();
            if (!address) {
                errors.push(timcalAdmin.strings.addressRequired);
                highlightField(addressTextarea);
                isValid = false;
            } else {
                clearFieldHighlight(addressTextarea);
            }
        }

        // Validate host selection
        const hostSelect = document.getElementById('timcal_host_user_id');
        if (hostSelect && !hostSelect.value) {
            errors.push('Please select a host for this event.');
            highlightField(hostSelect);
            isValid = false;
        } else if (hostSelect) {
            clearFieldHighlight(hostSelect);
        }

        // Display errors if any
        if (errors.length > 0) {
            displayValidationErrors(errors);
        } else {
            clearValidationErrors();
        }

        return isValid;
    }

    /**
     * Highlight a field with validation error.
     *
     * @param {HTMLElement} field The field element to highlight.
     */
    function highlightField(field) {
        field.classList.add('timcal-field-error');
        field.style.borderColor = '#dc3232';
    }

    /**
     * Clear field highlight.
     *
     * @param {HTMLElement} field The field element to clear.
     */
    function clearFieldHighlight(field) {
        field.classList.remove('timcal-field-error');
        field.style.borderColor = '';
    }

    /**
     * Display validation errors to the user.
     *
     * @param {Array} errors Array of error messages.
     */
    function displayValidationErrors(errors) {
        // Remove existing error notices
        clearValidationErrors();

        // Create error notice
        const notice = document.createElement('div');
        notice.className = 'notice notice-error timcal-validation-error';
        notice.innerHTML = '<p><strong>Please correct the following errors:</strong></p><ul>' +
            errors.map(error => '<li>' + escapeHtml(error) + '</li>').join('') +
            '</ul>';

        // Insert notice after the title
        const titleWrap = document.querySelector('#titlewrap');
        if (titleWrap) {
            titleWrap.parentNode.insertBefore(notice, titleWrap.nextSibling);
        }

        // Scroll to notice
        notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /**
     * Clear validation error notices.
     */
    function clearValidationErrors() {
        const errorNotices = document.querySelectorAll('.timcal-validation-error');
        errorNotices.forEach(notice => notice.remove());
    }

    /**
     * Initialize host select enhancement.
     */
    function initHostSelectEnhancement() {
        const hostSelect = document.getElementById('timcal_host_user_id');
        if (!hostSelect) {
            return;
        }

        // Add search functionality if there are many options
        if (hostSelect.options.length > 10) {
            enhanceSelectWithSearch(hostSelect);
        }
    }

    /**
     * Enhance select element with search functionality.
     *
     * @param {HTMLSelectElement} selectElement The select element to enhance.
     */
    function enhanceSelectWithSearch(selectElement) {
        // Create wrapper
        const wrapper = document.createElement('div');
        wrapper.className = 'timcal-select-wrapper';
        
        // Create search input
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'timcal-select-search';
        searchInput.placeholder = 'Search users...';
        
        // Store original options
        const originalOptions = Array.from(selectElement.options);
        
        // Insert wrapper and search input
        selectElement.parentNode.insertBefore(wrapper, selectElement);
        wrapper.appendChild(searchInput);
        wrapper.appendChild(selectElement);
        
        // Add search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            // Clear existing options
            selectElement.innerHTML = '';
            
            // Filter and add matching options
            originalOptions.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    selectElement.appendChild(option.cloneNode(true));
                }
            });
            
            // If no matches, show all options
            if (selectElement.options.length === 0) {
                originalOptions.forEach(option => {
                    selectElement.appendChild(option.cloneNode(true));
                });
            }
        });
    }

    /**
     * Escape HTML characters in a string.
     *
     * @param {string} text The text to escape.
     * @return {string} The escaped text.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Initialize when DOM is ready.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEventsAdmin);
    } else {
        initEventsAdmin();
    }

})();