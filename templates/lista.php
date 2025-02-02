<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$table_name = $wpdb->prefix . 'geralinks_urls';

// Buscar links do usuário atual se estiver logado
$user_id = get_current_user_id();
$where_clause = $user_id ? "WHERE user_id = $user_id" : "WHERE user_id IS NULL";

$order_by = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'data_criacao';
$order_dir = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'ASC' : 'DESC';

$links = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table_name $where_clause ORDER BY %s $order_dir",
        $order_by
    )
);
?>

<div class="geralinks-container">
    <div class="geralinks-lista">
        <h3 class="geralinks-lista-titulo">Seus Links Encurtados</h3>
        
        <div class="geralinks-controls">
            <div class="geralinks-search">
                <input type="text" id="geralinks-search-input" class="geralinks-input" placeholder="Pesquisar links...">
            </div>
            <div class="geralinks-sort">
                <select id="geralinks-sort" class="geralinks-input">
                    <option value="data_criacao-desc">Data (Mais recente)</option>
                    <option value="data_criacao-asc">Data (Mais antiga)</option>
                    <option value="clicks-desc">Cliques (Maior)</option>
                    <option value="clicks-asc">Cliques (Menor)</option>
                </select>
            </div>
        </div>

        <?php if (empty($links)) : ?>
            <div class="geralinks-lista-vazia">
                <p>Nenhum link encontrado.</p>
                <small>Use o formulário acima para criar seus links curtos.</small>
            </div>
        <?php else : ?>
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
                    <tbody>
                        <?php foreach ($links as $link): ?>
                        <tr>
                            <td class="data">
                                <?php echo date('d/m/Y H:i', strtotime($link->data_criacao)); ?>
                            </td>
                            <td class="url-original">
                                <?php echo esc_url($link->url_original); ?>
                            </td>
                            <td>
                                <div class="url-curta">
                                    <?php echo esc_url($link->url_curta); ?>
                                    <button type="button" class="geralinks-icon-button" onclick="copyToClipboard('<?php echo esc_js($link->url_curta); ?>', this)" title="Copiar link">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                            <td class="cliques">
                                <?php echo esc_html($link->clicks); ?>
                            </td>
                            <td class="acoes">
                                <a href="<?php echo esc_url($link->url_curta); ?>" target="_blank" class="geralinks-icon-button" title="Abrir link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                        <polyline points="15 3 21 3 21 9"></polyline>
                                        <line x1="10" y1="14" x2="21" y2="3"></line>
                                    </svg>
                                </a>
                                <button type="button" class="geralinks-icon-button delete" onclick="deletarLink(<?php echo esc_js($link->id); ?>, this)" title="Excluir link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 6h18"></path>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
