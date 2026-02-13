/**
 * Rext AI Admin JavaScript
 *
 * @package Rext_AI
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Main admin object
    var RextAI = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind all events
         */
        bindEvents: function() {
            // Copy to clipboard
            $(document).on('click', '.rext-ai-copy-btn', this.handleCopy);

            // Toggle API key visibility
            $('#rext-ai-toggle-key').on('click', this.handleToggleKey);

            // Regenerate API key
            $('#rext-ai-regenerate-key').on('click', this.handleRegenerate);

            // Export logs
            $('#rext-ai-export-logs').on('click', this.handleExportLogs);

            // Clear logs
            $('#rext-ai-clear-logs').on('click', this.handleClearLogs);

            // Toggle log data details
            $(document).on('click', '.rext-ai-toggle-data', this.handleToggleData);
        },

        /**
         * Handle copy to clipboard
         */
        handleCopy: function(e) {
            e.preventDefault();

            var targetId = $(this).data('target');
            var $target = $('#' + targetId);
            var text = $target.text().trim();

            // Use modern clipboard API if available
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    RextAI.showToast(rextAiAdmin.strings.copied, 'success');
                }).catch(function() {
                    RextAI.fallbackCopy(text);
                });
            } else {
                RextAI.fallbackCopy(text);
            }
        },

        /**
         * Fallback copy method
         */
        fallbackCopy: function(text) {
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();

            try {
                document.execCommand('copy');
                RextAI.showToast(rextAiAdmin.strings.copied, 'success');
            } catch (err) {
                RextAI.showToast(rextAiAdmin.strings.copyFailed, 'error');
            }

            $temp.remove();
        },

        /**
         * Handle toggle API key visibility
         */
        handleToggleKey: function(e) {
            e.preventDefault();

            var $masked = $('#rext-ai-api-key-masked');
            var $full = $('#rext-ai-api-key-full');
            var $btn = $(this);

            if ($full.is(':visible')) {
                $full.hide();
                $masked.show();
                $btn.text(rextAiAdmin.strings.show || 'Show');
            } else {
                $masked.hide();
                $full.show();
                $btn.text(rextAiAdmin.strings.hide || 'Hide');
            }
        },

        /**
         * Handle regenerate API key
         */
        handleRegenerate: function(e) {
            e.preventDefault();

            if (!confirm(rextAiAdmin.strings.confirmRegenerate)) {
                return;
            }

            var $btn = $(this);
            var originalText = $btn.text();

            $btn.prop('disabled', true).text(rextAiAdmin.strings.regenerating);

            $.ajax({
                url: rextAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rext_ai_regenerate_key',
                    nonce: rextAiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update displayed keys
                        $('#rext-ai-api-key-masked').text(response.data.masked_key);
                        $('#rext-ai-api-key-full').text(response.data.key);

                        RextAI.showToast(rextAiAdmin.strings.regenerateSuccess, 'success');
                    } else {
                        RextAI.showToast(response.data.message || rextAiAdmin.strings.regenerateError, 'error');
                    }
                },
                error: function() {
                    RextAI.showToast(rextAiAdmin.strings.regenerateError, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Handle export logs
         */
        handleExportLogs: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var originalText = $btn.html();
            var level = $('select[name="level"]').val() || '';

            $btn.prop('disabled', true).html('<span class="dashicons dashicons-download"></span> ' + rextAiAdmin.strings.exporting);

            $.ajax({
                url: rextAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rext_ai_export_logs',
                    nonce: rextAiAdmin.nonce,
                    level: level
                },
                success: function(response) {
                    if (response.success) {
                        // Create and download file
                        var blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
                        var link = document.createElement('a');
                        var url = URL.createObjectURL(blob);

                        link.setAttribute('href', url);
                        link.setAttribute('download', response.data.filename);
                        link.style.visibility = 'hidden';

                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        RextAI.showToast('Logs exported successfully', 'success');
                    } else {
                        RextAI.showToast('Failed to export logs', 'error');
                    }
                },
                error: function() {
                    RextAI.showToast('Failed to export logs', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        },

        /**
         * Handle clear logs
         */
        handleClearLogs: function(e) {
            e.preventDefault();

            if (!confirm(rextAiAdmin.strings.confirmClearLogs)) {
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true);

            $.ajax({
                url: rextAiAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rext_ai_clear_logs',
                    nonce: rextAiAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        RextAI.showToast(response.data.message, 'success');
                        // Reload page to show empty state
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        RextAI.showToast('Failed to clear logs', 'error');
                    }
                },
                error: function() {
                    RextAI.showToast('Failed to clear logs', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Handle toggle log data details
         */
        handleToggleData: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var logId = $btn.data('log-id');
            var $dataRow = $('#rext-ai-data-' + logId);

            $btn.toggleClass('active');
            $dataRow.toggle();
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            var $toast = $('#rext-ai-toast');

            // Remove existing classes
            $toast.removeClass('rext-ai-toast--success rext-ai-toast--error');

            // Add appropriate class
            if (type === 'success') {
                $toast.addClass('rext-ai-toast--success');
            } else if (type === 'error') {
                $toast.addClass('rext-ai-toast--error');
            }

            // Set message and show
            $toast.find('.rext-ai-toast__message').text(message);
            $toast.fadeIn(200);

            // Auto-hide after 3 seconds
            setTimeout(function() {
                $toast.fadeOut(200);
            }, 3000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        RextAI.init();
    });

    // Expose to global scope if needed
    window.RextAI = RextAI;

})(jQuery);
