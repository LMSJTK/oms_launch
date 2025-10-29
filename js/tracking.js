/**
 * OMS Launch Content Tracking JavaScript
 * Tracks interactions and hijacks SCORM API calls
 */

(function() {
    'use strict';

    // API base URL - will be set by launch.php
    const API_BASE = window.location.origin + '/api';

    // Initialize tracking
    function initTracking() {
        if (window.OMS_TRACKING.initialized) return;

        // Track content view
        trackView();

        // Setup interaction listeners
        setupInteractionListeners();

        // Hijack SCORM API
        hijackSCORM();

        window.OMS_TRACKING.initialized = true;
    }

    // Track content view
    function trackView() {
        if (!window.OMS_TRACKING.trackingLinkId) {
            console.warn('OMS Tracking: No tracking link ID found');
            return;
        }

        fetch(API_BASE + '/track_view.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                tracking_link_id: window.OMS_TRACKING.trackingLinkId
            })
        }).catch(err => console.error('Failed to track view:', err));
    }

    // Setup listeners for tagged elements
    function setupInteractionListeners() {
        // Find all elements with data-tag attribute
        const taggedElements = document.querySelectorAll('[data-tag]');

        taggedElements.forEach(element => {
            const tag = element.getAttribute('data-tag');

            // Determine event type based on element
            let eventType = 'change';
            if (element.tagName === 'BUTTON' || element.tagName === 'A') {
                eventType = 'click';
            } else if (element.tagName === 'INPUT' || element.tagName === 'SELECT' || element.tagName === 'TEXTAREA') {
                eventType = 'change';
            }

            // Add event listener
            element.addEventListener(eventType, function(e) {
                recordInteraction(tag, element);
            });
        });
    }

    // Record an interaction
    function recordInteraction(tag, element) {
        const interaction = {
            tag: tag,
            timestamp: new Date().toISOString(),
            element_type: element.tagName.toLowerCase(),
            value: getElementValue(element)
        };

        window.OMS_TRACKING.interactions.push(interaction);

        console.log('OMS Tracking: Recorded interaction', interaction);
    }

    // Get value from element
    function getElementValue(element) {
        if (element.tagName === 'INPUT') {
            if (element.type === 'checkbox' || element.type === 'radio') {
                return element.checked;
            }
            return element.value;
        } else if (element.tagName === 'SELECT') {
            return element.value;
        } else if (element.tagName === 'TEXTAREA') {
            return element.value;
        }
        return null;
    }

    // Hijack SCORM API
    function hijackSCORM() {
        // Create fake SCORM API object
        const SCORMAPI = {
            Initialize: function(param) {
                console.log('SCORM API: Initialize called');
                return 'true';
            },

            Terminate: function(param) {
                console.log('SCORM API: Terminate called');
                return 'true';
            },

            GetValue: function(element) {
                console.log('SCORM API: GetValue called for', element);
                return '';
            },

            SetValue: function(element, value) {
                console.log('SCORM API: SetValue called', element, value);

                // Track score setting
                if (element === 'cmi.core.score.raw' || element === 'cmi.score.raw') {
                    const score = parseInt(value);
                    if (!isNaN(score)) {
                        window.OMS_TRACKING.score = score;
                    }
                }

                // Track completion status
                if (element === 'cmi.core.lesson_status' || element === 'cmi.completion_status') {
                    if (value === 'completed' || value === 'passed') {
                        handleCompletion();
                    }
                }

                return 'true';
            },

            Commit: function(param) {
                console.log('SCORM API: Commit called');
                return 'true';
            },

            GetLastError: function() {
                return '0';
            },

            GetErrorString: function(errorCode) {
                return 'No error';
            },

            GetDiagnostic: function(errorCode) {
                return 'No error';
            }
        };

        // Expose SCORM API to window
        window.API = SCORMAPI;
        window.API_1484_11 = SCORMAPI; // SCORM 2004

        // Also create custom RecordTest function
        window.RecordTest = function(score) {
            console.log('RecordTest called with score:', score);

            if (typeof score !== 'undefined' && score !== null) {
                window.OMS_TRACKING.score = parseInt(score);
            }

            handleCompletion();
        };

        console.log('SCORM API hijacked successfully');
    }

    // Handle completion
    function handleCompletion() {
        if (!window.OMS_TRACKING.trackingLinkId) {
            console.warn('OMS Tracking: No tracking link ID found');
            return;
        }

        const completionData = {
            tracking_link_id: window.OMS_TRACKING.trackingLinkId,
            score: window.OMS_TRACKING.score || 0,
            interactions: window.OMS_TRACKING.interactions
        };

        console.log('OMS Tracking: Recording completion', completionData);

        fetch(API_BASE + '/track_completion.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(completionData)
        })
        .then(response => response.json())
        .then(data => {
            console.log('OMS Tracking: Completion recorded', data);
        })
        .catch(err => console.error('Failed to record completion:', err));
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTracking);
    } else {
        initTracking();
    }

    // Export functions for external use
    window.OMSTracking = {
        recordInteraction: recordInteraction,
        recordCompletion: handleCompletion,
        getInteractions: function() {
            return window.OMS_TRACKING.interactions;
        }
    };

})();
