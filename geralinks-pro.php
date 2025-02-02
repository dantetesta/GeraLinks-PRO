<?php
/**
 * Plugin Name: GeraLinks PRO
 * Plugin URI: https://dantetesta.com
 * Description: Sistema profissional de encurtador de links
 * Version: 1.0.0
 * Author: Dante Testa
 * Author URI: https://dantetesta.com
 * Text Domain: geralinks-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

// Definição de constantes
define('GERALINKS_PRO_VERSION', '1.0.0');
define('GERALINKS_PRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GERALINKS_PRO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Ativação do plugin
register_activation_hook(__FILE__, 'geralinks_pro_activate');
function geralinks_pro_activate() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'geralinks_urls';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        url_original text NOT NULL,
        url_curta varchar(255) NOT NULL,
        slug varchar(255),
        user_id bigint(20),
        clicks int DEFAULT 0,
        data_criacao datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Classe principal do plugin
class GeraLinksPro {
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('template_redirect', array($this, 'redirect_url_curta'));
    }

    public function init() {
        // Registrar shortcodes
        add_shortcode('geralinks_form', array($this, 'render_form'));
        add_shortcode('geralinks_lista', array($this, 'render_lista'));
        
        // Adicionar endpoint para processar URLs
        add_action('wp_ajax_geralinks_encurtar', array($this, 'ajax_encurtar_url'));
        add_action('wp_ajax_nopriv_geralinks_encurtar', array($this, 'ajax_encurtar_url'));

        // Adicionar endpoint para obter cliques
        add_action('wp_ajax_geralinks_get_clicks', array($this, 'ajax_get_clicks'));
        add_action('wp_ajax_nopriv_geralinks_get_clicks', array($this, 'ajax_get_clicks'));

        // Adicionar endpoint para deletar links
        add_action('wp_ajax_geralinks_deletar', array($this, 'ajax_deletar_link'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'geralinks-pro-style',
            GERALINKS_PRO_PLUGIN_URL . 'assets/css/style.css',
            array(),
            GERALINKS_PRO_VERSION
        );

        wp_enqueue_script(
            'geralinks-pro-script',
            GERALINKS_PRO_PLUGIN_URL . 'assets/js/script.js',
            array('jquery'),
            GERALINKS_PRO_VERSION,
            true
        );

        wp_localize_script('geralinks-pro-script', 'geralinksAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('geralinks_nonce')
        ));
    }

    public function render_form() {
        ob_start();
        include GERALINKS_PRO_PLUGIN_DIR . 'templates/form.php';
        return ob_get_clean();
    }

    public function render_lista() {
        ob_start();
        include GERALINKS_PRO_PLUGIN_DIR . 'templates/lista.php';
        return ob_get_clean();
    }

    public function ajax_encurtar_url() {
        check_ajax_referer('geralinks_nonce', 'nonce');

        $url_original = sanitize_text_field($_POST['url']);
        $slug = sanitize_text_field($_POST['slug']);

        if (empty($url_original)) {
            wp_send_json_error('URL original é obrigatória');
            return;
        }

        if (!filter_var($url_original, FILTER_VALIDATE_URL)) {
            wp_send_json_error('URL inválida');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'geralinks_urls';

        // Gerar slug único se não foi fornecido
        if (empty($slug)) {
            do {
                $slug = $this->gerar_slug();
                $existe = $wpdb->get_var(
                    $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE slug = %s", $slug)
                );
            } while ($existe > 0);
        } else {
            // Verificar se o slug já existe
            $existe = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE slug = %s", $slug)
            );
            if ($existe > 0) {
                wp_send_json_error('Este slug já está em uso. Por favor, escolha outro.');
                return;
            }
        }

        $user_id = get_current_user_id();
        $url_curta = home_url('/' . $slug);

        $resultado = $wpdb->insert(
            $table_name,
            array(
                'url_original' => $url_original,
                'url_curta' => $url_curta,
                'slug' => $slug,
                'user_id' => $user_id,
                'clicks' => 0,
                'data_criacao' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%d', '%s')
        );

        if ($resultado) {
            $link_id = $wpdb->insert_id;
            wp_send_json_success(array(
                'url_original' => $url_original,
                'url_curta' => $url_curta,
                'link_id' => $link_id,
                'data_criacao' => date_i18n('d/m/Y H:i', strtotime(current_time('mysql')))
            ));
        } else {
            wp_send_json_error('Erro ao criar URL curta');
        }
    }

    public function ajax_get_clicks() {
        check_ajax_referer('geralinks_nonce', 'nonce');
        
        $link_id = intval($_POST['link_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'geralinks_urls';
        
        $clicks = $wpdb->get_var($wpdb->prepare(
            "SELECT clicks FROM $table_name WHERE id = %d",
            $link_id
        ));
        
        wp_send_json_success(array('clicks' => $clicks));
    }

    public function ajax_deletar_link() {
        check_ajax_referer('geralinks_nonce', 'nonce');

        $link_id = intval($_POST['link_id']);
        if (!$link_id) {
            wp_send_json_error('ID do link inválido');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'geralinks_urls';
        
        // Verificar se o link pertence ao usuário atual
        $user_id = get_current_user_id();
        $link = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
                $link_id,
                $user_id
            )
        );

        if (!$link) {
            wp_send_json_error('Link não encontrado ou você não tem permissão para excluí-lo');
        }

        $deleted = $wpdb->delete(
            $table_name,
            array('id' => $link_id),
            array('%d')
        );

        if ($deleted) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Erro ao excluir o link');
        }
    }

    private function gerar_slug($length = 6) {
        $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $caracteres[rand(0, strlen($caracteres) - 1)];
        }
        return $slug;
    }

    public function redirect_url_curta() {
        // Pega o path da URL atual
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        // Se não houver path, retorna
        if (empty($path)) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'geralinks_urls';

        // Procura o slug no banco de dados
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE slug = %s",
            $path
        ));

        // Se encontrou o link
        if ($link) {
            // Incrementa o contador de cliques
            $wpdb->update(
                $table_name,
                array('clicks' => $link->clicks + 1),
                array('id' => $link->id),
                array('%d'),
                array('%d')
            );

            // Redireciona para a URL original
            wp_redirect($link->url_original, 301);
            exit;
        }
    }
}

// Inicializar o plugin
new GeraLinksPro();
