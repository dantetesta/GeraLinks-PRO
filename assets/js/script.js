jQuery(document).ready(function($) {
    // Cria o container de toast apenas se não existir
    if (!$('.geralinks-toast-container').length) {
        $('body').append('<div class="geralinks-toast-container"></div>');
    }

    // Função para mostrar toast
    function showToast(message) {
        const $container = $('.geralinks-toast-container');
        
        // Remove qualquer toast existente
        $('.geralinks-toast').remove();
        
        // Cria o novo toast
        const $toast = $(`
            <div class="geralinks-toast">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span>${message}</span>
            </div>
        `).appendTo($container);
        
        // Força um reflow antes de adicionar a classe show
        $toast[0].offsetHeight;
        
        // Adiciona a classe show para iniciar a animação
        $toast.addClass('show');
        
        // Remove o toast após 3 segundos
        setTimeout(() => {
            $toast.removeClass('show');
            setTimeout(() => $toast.remove(), 300);
        }, 3000);
    }

    // Função para copiar texto
    window.copyToClipboard = function(text, button) {
        const $button = $(button);
        
        navigator.clipboard.writeText(text).then(() => {
            // Salva o ícone original
            const originalHtml = $button.html();
            
            // Troca para o ícone de thumbsup
            $button.html(`
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                </svg>
            `).addClass('copied');
            
            // Volta ao ícone original após 2 segundos
            setTimeout(() => {
                $button.html(originalHtml).removeClass('copied');
            }, 2000);
        }).catch(function() {
            // Mostra toast de erro se falhar
            showToast('Erro ao copiar o link');
        });
    };

    // Função para deletar link
    window.deletarLink = function(linkId, button) {
        if (!confirm('Tem certeza que deseja excluir este link?')) {
            return;
        }

        const $button = $(button);
        const $row = $button.closest('tr');

        $.ajax({
            url: geralinksAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'geralinks_deletar',
                link_id: linkId,
                nonce: geralinksAjax.nonce
            },
            beforeSend: function() {
                // Adiciona classe de loading no botão
                $button.addClass('loading').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Remove a linha com animação
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Se não houver mais linhas, mostra mensagem
                        if ($('.geralinks-table tbody tr').length === 0) {
                            $('.geralinks-table tbody').append(`
                                <tr>
                                    <td colspan="4" class="no-links">
                                        Nenhum link encontrado
                                    </td>
                                </tr>
                            `);
                        }
                    });
                } else {
                    alert('Erro ao excluir o link. Por favor, tente novamente.');
                }
            },
            error: function() {
                alert('Erro ao excluir o link. Por favor, tente novamente.');
            },
            complete: function() {
                $button.removeClass('loading').prop('disabled', false);
            }
        });
    };

    // Pesquisa de links
    $('#geralinks-search-input').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.geralinks-table tbody tr').each(function() {
            const $row = $(this);
            const searchText = $row.data('search').toLowerCase();
            $row.toggleClass('hidden', !searchText.includes(searchTerm));
        });
    });

    // Ordenação de links
    $('#geralinks-sort').on('change', function() {
        const [field, direction] = $(this).val().split('-');
        const $tbody = $('.geralinks-table tbody');
        const rows = $tbody.find('tr').get();
        
        rows.sort(function(a, b) {
            let aValue, bValue;
            
            if (field === 'data_criacao') {
                aValue = new Date($(a).find('td:first').text().split('/').reverse().join('/'));
                bValue = new Date($(b).find('td:first').text().split('/').reverse().join('/'));
            } else if (field === 'clicks') {
                aValue = parseInt($(a).find('.geralinks-contador').text());
                bValue = parseInt($(b).find('.geralinks-contador').text());
            }
            
            if (direction === 'asc') {
                return aValue > bValue ? 1 : -1;
            } else {
                return aValue < bValue ? 1 : -1;
            }
        });
        
        $tbody.empty();
        $tbody.append(rows);
    });

    // Atualizar contador de cliques via AJAX
    function atualizarContador(linkId) {
        $.ajax({
            url: geralinksAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'geralinks_get_clicks',
                nonce: geralinksAjax.nonce,
                link_id: linkId
            },
            success: function(response) {
                if (response.success) {
                    $('.geralinks-contador[data-link-id="' + linkId + '"]').text(response.data.clicks);
                }
            }
        });
    }

    // Atualizar contadores a cada 30 segundos
    setInterval(function() {
        $('.geralinks-contador').each(function() {
            const linkId = $(this).data('link-id');
            atualizarContador(linkId);
        });
    }, 30000);

    // Função para adicionar novo link à tabela
    function adicionarLinkNaTabela(link) {
        const novaLinha = `
            <tr data-id="${link.id}" data-search="${link.url_original} ${link.url_curta}">
                <td class="geralinks-date">${link.data_criacao}</td>
                <td class="geralinks-url">
                    <a href="${link.url_original}" target="_blank" class="geralinks-link">
                        ${link.url_original}
                    </a>
                </td>
                <td class="geralinks-url">
                    <div class="geralinks-short-url">
                        <a href="${link.url_curta}" target="_blank" class="geralinks-link">
                            ${link.url_curta}
                        </a>
                        <button type="button" class="geralinks-icon-button geralinks-copy" onclick="copyToClipboard('${link.url_curta}', this)" title="Copiar link">
                            <div class="geralinks-tooltip">Copiado!</div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                        </button>
                    </div>
                </td>
                <td class="geralinks-clicks">
                    <span class="geralinks-contador" data-link-id="${link.id}">
                        0
                    </span>
                </td>
                <td class="geralinks-actions">
                    <button type="button" class="geralinks-icon-button geralinks-delete" onclick="deletarLink(${link.id}, this)" title="Excluir link">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                            <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </td>
            </tr>
        `;

        // Se a tabela não existir, criar
        if ($('.geralinks-lista-vazia').length) {
            $('.geralinks-lista-vazia').replaceWith(`
                <div class="geralinks-table-wrapper">
                    <table class="geralinks-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>URL Original</th>
                                <th>URL Curta</th>
                                <th>Cliques</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            `);
        }

        // Adicionar nova linha no topo da tabela
        const $tbody = $('.geralinks-table tbody');
        $tbody.prepend(novaLinha);
        
        // Animar a entrada da nova linha
        const $novaLinha = $tbody.find('tr:first');
        $novaLinha.hide().fadeIn(300);
    }

    // Formulário de criação de link
    $('#geralinks-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitButton = $form.find('button[type="submit"]');
        const $resultado = $('.geralinks-resultado');
        
        $submitButton.prop('disabled', true);
        
        $.ajax({
            url: geralinksAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'geralinks_encurtar',
                nonce: geralinksAjax.nonce,
                url: $('#url-original').val(),
                slug: $('#url-slug').val()
            },
            success: function(response) {
                if (response.success) {
                    // Adicionar o novo link à tabela se ela existir
                    if ($('.geralinks-lista').length) {
                        adicionarLinkNaTabela({
                            id: response.data.link_id,
                            url_original: response.data.url_original,
                            url_curta: response.data.url_curta,
                            data_criacao: response.data.data_criacao
                        });
                    }
                    
                    // Mostrar mensagem de sucesso
                    $resultado
                        .html(`
                            <div class="geralinks-resultado-item">
                                <div class="geralinks-url-content">
                                    <span class="geralinks-url-text">${response.data.url_curta}</span>
                                </div>
                                <button type="button" class="geralinks-copy-button" onclick="copyToClipboard('${response.data.url_curta}', this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                    Copiar
                                </button>
                            </div>
                        `)
                        .hide()
                        .fadeIn(300);
                    
                    $form[0].reset();
                } else {
                    $resultado
                        .html(`<p style="color: red;">Erro: ${response.data}</p>`)
                        .slideDown();
                }
            },
            error: function() {
                $resultado
                    .html('<p style="color: red;">Erro ao processar a solicitação. Tente novamente.</p>')
                    .slideDown();
            },
            complete: function() {
                $submitButton.prop('disabled', false);
            }
        });
    });
});
