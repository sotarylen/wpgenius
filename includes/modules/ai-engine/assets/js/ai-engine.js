/**
 * AI Content Engine JavaScript
 *
 * @package WP_Genius
 */

(function($) {
    'use strict';

    // Global state
    let selectedProvider = '';
    let selectedModel = '';
    let selectedPromptId = '';
    let generatedContent = '';

    /**
     * Initialize
     */
    function init() {
        // Provider change
        $('#ai-provider, #ai-schedule-provider').on('change', handleProviderChange);

        // Prompt change
        $('#ai-prompt, #ai-schedule-prompt').on('change', handlePromptChange);

        // Generate button
        $('#ai-generate-btn').on('click', handleGenerate);

        // Save as draft
        $('#ai-save-as-draft-btn').on('click', handleSaveAsDraft);

        // Copy button
        $('#ai-copy-btn').on('click', handleCopy);

        // Create post button
        $('#ai-create-post-btn').on('click', handleCreatePost);

        // Validate key
        $('.ai-validate-key').on('click', handleValidateKey);

        // Save prompt
        $('#ai-save-prompt-btn').on('click', handleSavePrompt);

        // Add prompt button
        $('#ai-add-prompt-btn').on('click', handleAddPrompt);

        // Edit prompt
        $('.ai-edit-prompt').on('click', handleEditPrompt);

        // Delete prompt
        $('.ai-delete-prompt').on('click', handleDeletePrompt);

        // Use template
        $('.ai-use-template').on('click', handleUseTemplate);

        // Add schedule button
        $('#ai-add-schedule-btn').on('click', handleAddSchedule);

        // Edit schedule
        $('.ai-edit-schedule').on('click', handleEditSchedule);

        // Delete schedule
        $('.ai-delete-schedule').on('click', handleDeleteSchedule);

        // Toggle schedule
        $('.ai-toggle-schedule').on('click', handleToggleSchedule);

        // Save schedule
        $('#ai-save-schedule-btn').on('click', handleSaveSchedule);

        // Process queue
        $('#ai-process-queue-btn').on('click', handleProcessQueue);

        // Queue status filter
        $('#ai-queue-status-filter').on('change', handleQueueFilter);

        // Modal close
        $('.w2p-ai-modal-close').on('click', closeModal);

        // Template variable detection
        $('#ai-prompt-template').on('input', detectTemplateVariables);

        // Save settings
        $('#ai-save-settings').on('click', handleSaveSettings);
    }

    /**
     * Handle provider change
     */
    function handleProviderChange() {
        const $select = $(this);
        const provider = $select.val();
        const $modelSelect = $select.closest('.w2p-ai-tab-content').find('.w2p-ai-select[id$="-model"]');

        if (!provider) {
            $modelSelect.prop('disabled', true).html('<option value="">' + w2pAIEngine.i18n.selectProvider + '</option>');
            return;
        }

        selectedProvider = provider;

        // Fetch models
        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_get_models',
            nonce: w2pAIEngine.nonce,
            provider: provider
        }, function(response) {
            if (response.success && response.data.models) {
                let options = '<option value="">' + w2pAIEngine.i18n.selectModel + '</option>';
                response.data.models.forEach(function(model) {
                    options += '<option value="' + model.id + '">' + model.name + ' - ' + model.desc + '</option>';
                });
                $modelSelect.prop('disabled', false).html(options);
            }
        });
    }

    /**
     * Handle prompt change
     */
    function handlePromptChange() {
        const promptId = $(this).val();
        selectedPromptId = promptId;

        if (!promptId) {
            $('#ai-variables-container').hide();
            return;
        }

        // Fetch prompt details
        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_get_prompt',
            nonce: w2pAIEngine.nonce,
            prompt_id: promptId
        }, function(response) {
            if (response.success && response.data.prompt) {
                renderVariables(response.data.prompt.variables);
            }
        });
    }

    /**
     * Render variable fields
     */
    function renderVariables(variables) {
        const $container = $('#ai-variables');
        $container.empty();

        if (!variables || Object.keys(variables).length === 0) {
            $('#ai-variables-container').hide();
            return;
        }

        Object.keys(variables).forEach(function(key) {
            const field = variables[key];
            let fieldHtml = '<div class="w2p-ai-variable-field">';
            fieldHtml += '<label for="ai-var-' + key + '">' + (field.label || key) + '</label>';

            switch (field.type) {
                case 'textarea':
                    fieldHtml += '<textarea id="ai-var-' + key + '" rows="3" placeholder="' + (field.placeholder || '') + '">';
                    fieldHtml += (field.default || '');
                    fieldHtml += '</textarea>';
                    break;
                case 'select':
                    fieldHtml += '<select id="ai-var-' + key + '">';
                    (field.options || []).forEach(function(opt) {
                        fieldHtml += '<option value="' + opt + '">' + opt + '</option>';
                    });
                    fieldHtml += '</select>';
                    break;
                case 'number':
                    fieldHtml += '<input type="number" id="ai-var-' + key + '" value="' + (field.default || '') + '">';
                    break;
                default:
                    fieldHtml += '<input type="text" id="ai-var-' + key + '" value="' + (field.default || '') + '" placeholder="' + (field.placeholder || '') + '">';
            }

            fieldHtml += '</div>';
            $container.append(fieldHtml);
        });

        $('#ai-variables-container').show();
    }

    /**
     * Handle generate
     */
    function handleGenerate() {
        const $btn = $(this);
        const provider = $('#ai-provider').val();
        const model = $('#ai-model').val();
        const promptId = $('#ai-prompt').val();
        const quantity = parseInt($('#ai-quantity').val()) || 1;

        if (!provider || !model || !promptId) {
            alert(w2pAIEngine.i18n.fillRequired);
            return;
        }

        // Collect variables
        const variables = {};
        $('#ai-variables .w2p-ai-variable-field').each(function() {
            const key = $(this).find('input, textarea, select').attr('id').replace('ai-var-', '');
            variables[key] = $(this).find('input, textarea, select').val();
        });

        $btn.prop('disabled', true).find('.dashicons').show();

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_generate',
            nonce: w2pAIEngine.nonce,
            provider: provider,
            model: model,
            prompt_id: promptId,
            quantity: quantity,
            variables: variables
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').hide();

            if (response.success) {
                generatedContent = response.data.content.map(c => c.content).join('\n\n---\n\n');
                $('#ai-result-content').html(marked.parse(generatedContent));
                $('#ai-result-section').show();
                $('#ai-save-as-draft-btn').show();
                $('#ai-usage-tokens').text(response.data.usage ? (response.data.usage.prompt_tokens + response.data.usage.completion_tokens) : 0);
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        }).fail(function() {
            $btn.prop('disabled', false).find('.dashicons').hide();
            alert(w2pAIEngine.i18n.error);
        });
    }

    /**
     * Handle save as draft
     */
    function handleSaveAsDraft() {
        if (!generatedContent) return;

        // Parse content
        const lines = generatedContent.split('\n');
        let title = '';
        let body = generatedContent;

        for (let i = 0; i < lines.length; i++) {
            const match = lines[i].match(/^#+\s+(.+)$/);
            if (match) {
                title = match[1];
                body = lines.slice(i + 1).join('\n').trim();
                break;
            }
        }

        if (!title) {
            title = generatedContent.substring(0, 100);
        }

        // Create post
        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_create_post',
            nonce: w2pAIEngine.nonce,
            title: title,
            content: body,
            status: 'draft'
        }, function(response) {
            if (response.success) {
                window.location.href = response.data.edit_url;
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Handle copy
     */
    function handleCopy() {
        if (!generatedContent) return;

        navigator.clipboard.writeText(generatedContent).then(function() {
            const $btn = $('#ai-copy-btn');
            const originalText = $btn.text();
            $btn.text('Copied!');
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        });
    }

    /**
     * Handle create post
     */
    function handleCreatePost() {
        if (!generatedContent) return;

        // Parse content
        const lines = generatedContent.split('\n');
        let title = '';
        let body = generatedContent;

        for (let i = 0; i < lines.length; i++) {
            const match = lines[i].match(/^#+\s+(.+)$/);
            if (match) {
                title = match[1];
                body = lines.slice(i + 1).join('\n').trim();
                break;
            }
        }

        if (!title) {
            title = generatedContent.substring(0, 100);
        }

        // Create post
        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_create_post',
            nonce: w2pAIEngine.nonce,
            title: title,
            content: body,
            status: 'draft'
        }, function(response) {
            if (response.success) {
                window.location.href = response.data.edit_url;
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Handle validate key
     */
    function handleValidateKey() {
        const $btn = $(this);
        const provider = $btn.data('provider');
        const apiKey = $('#ai-key-' + provider).val();

        if (!apiKey) {
            alert(w2pAIEngine.i18n.enterApiKey);
            return;
        }

        $btn.prop('disabled', true).text(w2pAIEngine.i18n.validating);

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_validate_key',
            nonce: w2pAIEngine.nonce,
            provider: provider,
            api_key: apiKey
        }, function(response) {
            $btn.prop('disabled', false).text(w2pAIEngine.i18n.validate);

            const $status = $('#ai-key-status-' + provider);
            if (response.success) {
                $status.html('<span class="w2p-ai-status-active">' + w2pAIEngine.i18n.valid + '</span>');
            } else {
                $status.html('<span class="w2p-ai-status-inactive">' + w2pAIEngine.i18n.invalid + '</span>');
            }
        });
    }

    /**
     * Handle save prompt
     */
    function handleSavePrompt() {
        const $btn = $(this);
        const id = $('#ai-prompt-id').val();
        const name = $('#ai-prompt-name').val();
        const type = $('#ai-prompt-type').val();
        const template = $('#ai-prompt-template').val();
        const temperature = parseFloat($('#ai-prompt-temperature').val()) || 0.7;
        const maxTokens = parseInt($('#ai-prompt-max-tokens').val()) || 2000;

        if (!name || !template) {
            alert(w2pAIEngine.i18n.fillRequired);
            return;
        }

        $btn.prop('disabled', true);

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_save_prompt',
            nonce: w2pAIEngine.nonce,
            id: id,
            name: name,
            type: type,
            template: template,
            temperature: temperature,
            max_tokens: maxTokens
        }, function(response) {
            $btn.prop('disabled', false);

            if (response.success) {
                closeModal();
                location.reload();
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Handle add prompt
     */
    function handleAddPrompt() {
        $('#ai-prompt-modal-title').text('Add New Prompt');
        $('#ai-prompt-id').val('');
        $('#ai-prompt-name').val('');
        $('#ai-prompt-type').val('custom');
        $('#ai-prompt-template').val('');
        $('#ai-prompt-temperature').val('0.7');
        $('#ai-prompt-max-tokens').val('2000');
        $('#ai-prompt-variables-section').hide();
        openModal('ai-prompt-modal');
    }

    /**
     * Handle edit prompt
     */
    function handleEditPrompt(e) {
        e.preventDefault();
        const promptId = $(this).data('id');

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_get_prompt',
            nonce: w2pAIEngine.nonce,
            prompt_id: promptId
        }, function(response) {
            if (response.success && response.data.prompt) {
                const prompt = response.data.prompt;
                $('#ai-prompt-modal-title').text('Edit Prompt');
                $('#ai-prompt-id').val(prompt.id);
                $('#ai-prompt-name').val(prompt.name);
                $('#ai-prompt-type').val(prompt.type);
                $('#ai-prompt-template').val(prompt.template);
                $('#ai-prompt-temperature').val(prompt.temperature);
                $('#ai-prompt-max-tokens').val(prompt.max_tokens);
                openModal('ai-prompt-modal');
            }
        });
    }

    /**
     * Handle delete prompt
     */
    function handleDeletePrompt(e) {
        e.preventDefault();
        if (!confirm(w2pAIEngine.i18n.confirmDelete)) return;

        const promptId = $(this).data('id');

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_delete_prompt',
            nonce: w2pAIEngine.nonce,
            prompt_id: promptId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Handle use template
     */
    function handleUseTemplate() {
        const name = $(this).data('name');
        const template = $(this).data('template');
        const variables = $(this).data('variables');

        $('#ai-prompt-modal-title').text('Add New Prompt');
        $('#ai-prompt-id').val('');
        $('#ai-prompt-name').val(name);
        $('#ai-prompt-type').val('custom');
        $('#ai-prompt-template').val(template);

        openModal('ai-prompt-modal');
    }

    /**
     * Handle add schedule
     */
    function handleAddSchedule() {
        $('#ai-schedule-modal-title').text('Add New Schedule');
        $('#ai-schedule-id').val('');
        $('#ai-schedule-name').val('');
        $('#ai-schedule-provider').val('');
        $('#ai-schedule-model').val('').prop('disabled', true);
        $('#ai-schedule-prompt').val('');
        $('#ai-schedule-frequency').val('daily');
        $('#ai-schedule-time').val('09:00');
        $('#ai-schedule-quantity').val('1');
        $('#ai-schedule-status').val('draft');
        $('#ai-schedule-featured').prop('checked', false);
        $('#ai-schedule-variables-container').hide();
        openModal('ai-schedule-modal');
    }

    /**
     * Handle edit schedule
     */
    function handleEditSchedule(e) {
        e.preventDefault();
        const scheduleId = $(this).data('id');

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_get_schedule',
            nonce: w2pAIEngine.nonce,
            schedule_id: scheduleId
        }, function(response) {
            if (response.success && response.data.schedule) {
                const schedule = response.data.schedule;
                $('#ai-schedule-modal-title').text('Edit Schedule');
                $('#ai-schedule-id').val(schedule.id);
                $('#ai-schedule-name').val(schedule.name);
                $('#ai-schedule-provider').val(schedule.provider).trigger('change');
                $('#ai-schedule-prompt').val(schedule.prompt_id);
                $('#ai-schedule-frequency').val(schedule.frequency);
                $('#ai-schedule-time').val(schedule.time);
                $('#ai-schedule-quantity').val(schedule.quantity);
                $('#ai-schedule-status').val(schedule.status);
                $('#ai-schedule-featured').prop('checked', schedule.featured);
                openModal('ai-schedule-modal');
            }
        });
    }

    /**
     * Handle delete schedule
     */
    function handleDeleteSchedule(e) {
        e.preventDefault();
        if (!confirm(w2pAIEngine.i18n.confirmDelete)) return;

        const scheduleId = $(this).data('id');

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_delete_schedule',
            nonce: w2pAIEngine.nonce,
            schedule_id: scheduleId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Handle toggle schedule
     */
    function handleToggleSchedule(e) {
        e.preventDefault();
        const scheduleId = $(this).data('id');

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_toggle_schedule',
            nonce: w2pAIEngine.nonce,
            schedule_id: scheduleId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Handle save schedule
     */
    function handleSaveSchedule() {
        const $btn = $(this);
        const id = $('#ai-schedule-id').val();
        const name = $('#ai-schedule-name').val();
        const provider = $('#ai-schedule-provider').val();
        const model = $('#ai-schedule-model').val();
        const promptId = $('#ai-schedule-prompt').val();
        const frequency = $('#ai-schedule-frequency').val();
        const time = $('#ai-schedule-time').val();
        const quantity = parseInt($('#ai-schedule-quantity').val()) || 1;
        const status = $('#ai-schedule-status').val();
        const featured = $('#ai-schedule-featured').is(':checked');

        if (!name || !provider || !model || !promptId) {
            alert(w2pAIEngine.i18n.fillRequired);
            return;
        }

        $btn.prop('disabled', true);

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_save_schedule',
            nonce: w2pAIEngine.nonce,
            id: id,
            name: name,
            provider: provider,
            model: model,
            prompt_id: promptId,
            frequency: frequency,
            time: time,
            quantity: quantity,
            status: status,
            featured: featured
        }, function(response) {
            $btn.prop('disabled', false);

            if (response.success) {
                closeModal();
                location.reload();
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Handle process queue
     */
    function handleProcessQueue() {
        const $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('spin');

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_process_queue',
            nonce: w2pAIEngine.nonce
        }, function(response) {
            $btn.prop('disabled', false).find('.dashicons').removeClass('spin');
            location.reload();
        });
    }

    /**
     * Handle queue filter
     */
    function handleQueueFilter() {
        const status = $(this).val();
        // Reload with filter
        window.location.href = w2pAIEngine.ajax_url.replace('admin-ajax.php', 'admin.php?page=wp-genius-ai-engine&tab=queue&status=' + status);
    }

    /**
     * Handle save settings
     */
    function handleSaveSettings(e) {
        e.preventDefault();

        const $form = $('#ai-settings-form');
        const $btn = $(this);

        $btn.prop('disabled', true);

        $.post(w2pAIEngine.ajax_url, {
            action: 'w2p_ai_save_settings',
            nonce: w2pAIEngine.nonce,
            api_keys: {
                openai: $('#ai-key-openai').val(),
                anthropic: $('#ai-key-anthropic').val(),
                gemini: $('#ai-key-gemini').val(),
                deepseek: $('#ai-key-deepseek').val()
            }
        }, function(response) {
            $btn.prop('disabled', false);

            if (response.success) {
                alert(w2pAIEngine.i18n.settingsSaved);
            } else {
                alert(response.data.message || w2pAIEngine.i18n.error);
            }
        });
    }

    /**
     * Detect template variables
     */
    function detectTemplateVariables() {
        const template = $(this).val();
        const matches = template.match(/\{\{(\w+)\}\}/g);

        if (matches && matches.length > 0) {
            const variables = [...new Set(matches.map(m => m.replace(/\{\{|\}\}/g, '')))];
            let html = '<ul>';
            variables.forEach(function(v) {
                html += '<li><code>' + v + '</code></li>';
            });
            html += '</ul>';
            $('#ai-prompt-variables-list').html(html);
            $('#ai-prompt-variables-section').show();
        } else {
            $('#ai-prompt-variables-section').hide();
        }
    }

    /**
     * Open modal
     */
    function openModal(modalId) {
        $('#' + modalId).show();
    }

    /**
     * Close modal
     */
    function closeModal() {
        $('.w2p-ai-modal').hide();
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
