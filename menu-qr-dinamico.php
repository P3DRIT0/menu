<?php
/**
 * Plugin Name: Menu QR Dinamico
 * Description: Gestiona una carta dinamica con secciones plegables, ajuste global de precios y botones para PDFs.
 * Version: 1.4.0
 * Author: Codex
 * Text Domain: menu-qr-dinamico
 */

if (! defined('ABSPATH')) {
    exit;
}

$mqd_autoload = plugin_dir_path(__FILE__) . 'vendor/autoload.php';
if (file_exists($mqd_autoload)) {
    require_once $mqd_autoload;
}

final class Menu_QR_Dinamico
{
    private const OPTION_MENU = 'mqd_menu_data';
    private const OPTION_PDFS = 'mqd_pdf_buttons';
    private const OPTION_PUBLIC_URL = 'mqd_public_menu_url';
    private const OPTION_PRICE_SETTINGS = 'mqd_price_settings';
    private const OPTION_PDF_ASSETS = 'mqd_pdf_assets';
    private const OPTION_VERSION = 'mqd_plugin_version';
    private const PAGE_SLUG = 'menu-qr-dinamico';

    private static ?Menu_QR_Dinamico $instance = null;
    private string $admin_hook = '';

    public static function boot(): Menu_QR_Dinamico
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void
    {
        if (! get_option(self::OPTION_MENU)) {
            add_option(self::OPTION_MENU, self::default_menu());
        }

        if (! get_option(self::OPTION_PDFS)) {
            add_option(self::OPTION_PDFS, self::default_pdf_buttons());
        }

        if (get_option(self::OPTION_PUBLIC_URL, null) === null) {
            add_option(self::OPTION_PUBLIC_URL, '');
        }

        if (get_option(self::OPTION_PRICE_SETTINGS, null) === null) {
            add_option(self::OPTION_PRICE_SETTINGS, self::default_price_settings());
        }

        if (get_option(self::OPTION_PDF_ASSETS, null) === null) {
            add_option(self::OPTION_PDF_ASSETS, self::default_pdf_assets());
        }

        update_option(self::OPTION_VERSION, '1.4.0');
    }

    private function __construct()
    {
        $this->maybe_upgrade();
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets'], 99);
        add_action('admin_post_mqd_save_menu', [$this, 'handle_save_menu']);
        add_action('admin_post_mqd_adjust_prices', [$this, 'handle_adjust_prices']);
        add_action('admin_post_preview_menu_pdf', [$this, 'handle_preview_menu_pdf']);
        add_action('admin_post_download_menu_pdf', [$this, 'handle_download_menu_pdf']);
        add_action('admin_post_nopriv_download_menu_pdf', [$this, 'handle_download_menu_pdf']);
        add_shortcode('menu_qr_dinamico', [$this, 'render_shortcode']);
    }

    public function register_admin_page(): void
    {
        $this->admin_hook = add_menu_page(
            'Carta dinamica',
            'Carta dinamica',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_admin_page'],
            'dashicons-food',
            58
        );
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== $this->admin_hook) {
            return;
        }

        $admin_css_path = plugin_dir_path(__FILE__) . 'assets/css/admin.css';
        $admin_js_path = plugin_dir_path(__FILE__) . 'assets/js/admin.js';

        wp_enqueue_media();
        wp_enqueue_style(
            'mqd-admin',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            [],
            file_exists($admin_css_path) ? (string) filemtime($admin_css_path) : '1.2.0'
        );
        wp_enqueue_script(
            'mqd-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            ['jquery'],
            file_exists($admin_js_path) ? (string) filemtime($admin_js_path) : '1.2.0',
            true
        );

        wp_localize_script(
            'mqd-admin',
            'mqdAdmin',
            [
                'sectionTemplate' => $this->get_section_template(),
                'itemTemplate'    => $this->get_item_template(),
                'buttonTemplate'  => $this->get_pdf_button_template(),
                'mediaTitle'      => 'Selecciona o sube un PDF',
                'mediaButton'     => 'Usar este archivo',
                'imageTitle'      => 'Selecciona o sube una imagen',
                'imageButton'     => 'Usar esta imagen',
            ]
        );
    }

    public function enqueue_front_assets(): void
    {
        $front_css_path = plugin_dir_path(__FILE__) . 'assets/css/front.css';
        $front_js_path = plugin_dir_path(__FILE__) . 'assets/js/front.js';

        wp_enqueue_style(
            'mqd-front',
            plugin_dir_url(__FILE__) . 'assets/css/front.css',
            [],
            file_exists($front_css_path) ? (string) filemtime($front_css_path) : '1.2.0'
        );
        wp_enqueue_script(
            'mqd-front',
            plugin_dir_url(__FILE__) . 'assets/js/front.js',
            [],
            file_exists($front_js_path) ? (string) filemtime($front_js_path) : '1.2.0',
            true
        );
    }

    public function render_admin_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $menu_data = get_option(self::OPTION_MENU, self::default_menu());
        $pdf_buttons = get_option(self::OPTION_PDFS, self::default_pdf_buttons());
        $public_menu_url = get_option(self::OPTION_PUBLIC_URL, '');
        $price_settings = $this->get_price_settings();
        $pdf_assets = $this->get_pdf_assets();
        $qr_image_url = $public_menu_url !== '' ? $this->build_qr_image_url($public_menu_url, 320) : '';
        $notice = isset($_GET['mqd_notice']) ? sanitize_text_field(wp_unslash($_GET['mqd_notice'])) : '';
        ?>
        <div class="wrap mqd-admin-wrap">
            <h1>Carta dinamica</h1>
            <p>Gestiona la carta que vera el cliente desde un solo QR y actualiza precios en bloque cuando lo necesites.</p>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p>Carta guardada correctamente.</p></div>
            <?php elseif ($notice === 'adjusted') : ?>
                <div class="notice notice-success is-dismissible"><p>El ajuste global de precios se ha guardado correctamente.</p></div>
            <?php endif; ?>

            <div class="mqd-admin-grid">
                <div class="mqd-panel">
                    <h2>Subida global de precios</h2>
                    <p>Activa o desactiva un porcentaje global sin perder los precios originales. Los valores como <strong>S/M</strong> no se tocan.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mqd_adjust_prices'); ?>
                        <input type="hidden" name="action" value="mqd_adjust_prices">
                        <div class="mqd-inline-fields">
                            <label for="mqd_percentage">Porcentaje</label>
                            <input id="mqd_percentage" type="number" name="percentage" step="0.01" value="<?php echo esc_attr((string) $price_settings['percentage']); ?>" required>
                            <label class="mqd-switch-field" for="mqd_price_enabled">
                                <input id="mqd_price_enabled" type="checkbox" name="price_enabled" value="1" <?php checked(! empty($price_settings['enabled'])); ?>>
                                <span>Activar ajuste</span>
                            </label>
                            <button type="submit" class="button button-primary">Guardar ajuste</button>
                        </div>
                        <p class="description">Estado actual: <strong><?php echo ! empty($price_settings['enabled']) ? 'ajuste activo' : 'precios normales'; ?></strong></p>
                    </form>
                </div>

                <div class="mqd-panel">
                    <h2>Shortcode</h2>
                    <p>Inserta <code>[menu_qr_dinamico]</code> en la pagina donde quieras mostrar la carta.</p>
                    <p>
                        <a
                            class="button button-secondary"
                            href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=preview_menu_pdf'), 'menu_pdf_action')); ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Previsualizar PDF
                        </a>
                    </p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mqd-main-form">
                <?php wp_nonce_field('mqd_save_menu'); ?>
                <input type="hidden" name="action" value="mqd_save_menu">

                <div class="mqd-panel">
                    <div class="mqd-panel-head">
                        <h2>QR de la carta</h2>
                    </div>
                    <div class="mqd-qr-layout">
                        <label class="mqd-field">
                            <span>URL publica de la carta</span>
                            <input
                                type="url"
                                name="public_menu_url"
                                value="<?php echo esc_attr($public_menu_url); ?>"
                                placeholder="https://tu-dominio.com/carta"
                            >
                            <small>Pega la URL completa de la pagina que contiene el shortcode <code>[menu_qr_dinamico]</code>.</small>
                        </label>

                        <div class="mqd-qr-preview">
                            <?php if ($qr_image_url !== '') : ?>
                                <img src="<?php echo esc_url($qr_image_url); ?>" alt="QR de la carta" width="240" height="240">
                                <div class="mqd-qr-actions">
                                    <a class="button button-secondary" href="<?php echo esc_url($qr_image_url); ?>" target="_blank" rel="noopener noreferrer">Abrir QR</a>
                                    <a class="button button-primary" href="<?php echo esc_url($public_menu_url); ?>" target="_blank" rel="noopener noreferrer">Abrir carta</a>
                                </div>
                            <?php else : ?>
                                <p class="mqd-qr-empty">Guarda una URL publica para ver aqui el QR listo para imprimir o compartir.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mqd-panel">
                    <div class="mqd-panel-head">
                        <h2>Diseno PDF</h2>
                    </div>
                    <div class="mqd-pdf-assets">
                        <div class="mqd-pdf-row">
                            <label class="mqd-field mqd-pdf-url">
                                <span>Imagen de portada</span>
                                <input type="url" name="pdf_assets[cover_image]" value="<?php echo esc_attr($pdf_assets['cover_image']); ?>" placeholder="https://.../portada.jpg">
                            </label>
                            <div class="mqd-pdf-actions">
                                <button type="button" class="button" data-mqd-pick-image>Elegir imagen</button>
                            </div>
                        </div>
                        <div class="mqd-pdf-row">
                            <label class="mqd-field mqd-pdf-url">
                                <span>Imagen de fondo para paginas interiores</span>
                                <input type="url" name="pdf_assets[background_image]" value="<?php echo esc_attr($pdf_assets['background_image']); ?>" placeholder="https://.../fondo.jpg">
                            </label>
                            <div class="mqd-pdf-actions">
                                <button type="button" class="button" data-mqd-pick-image>Elegir imagen</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mqd-panel">
                    <div class="mqd-panel-head">
                        <h2>Botones PDF</h2>
                        <button type="button" class="button" data-mqd-add-button>Anadir boton PDF</button>
                    </div>
                    <div class="mqd-pdf-buttons" data-mqd-buttons>
                        <?php foreach ($pdf_buttons as $index => $button) : ?>
                            <?php $this->render_pdf_button_fields($index, $button); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mqd-panel">
                    <div class="mqd-panel-head">
                        <h2>Secciones de la carta</h2>
                        <button type="button" class="button button-secondary" data-mqd-add-section>Anadir seccion</button>
                    </div>
                    <div class="mqd-sections" data-mqd-sections>
                        <?php foreach ($menu_data as $section_index => $section) : ?>
                            <?php $this->render_section_fields($section_index, $section); ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Guardar carta</button>
                </p>
            </form>
        </div>
        <?php
    }

    public function handle_save_menu(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('No tienes permisos para editar la carta.');
        }

        check_admin_referer('mqd_save_menu');

        $sections = isset($_POST['sections']) ? wp_unslash($_POST['sections']) : [];
        $buttons = isset($_POST['pdf_buttons']) ? wp_unslash($_POST['pdf_buttons']) : [];
        $public_menu_url = isset($_POST['public_menu_url']) ? esc_url_raw((string) wp_unslash($_POST['public_menu_url'])) : '';
        $pdf_assets = isset($_POST['pdf_assets']) ? wp_unslash($_POST['pdf_assets']) : [];

        update_option(self::OPTION_MENU, $this->sanitize_sections($sections));
        update_option(self::OPTION_PDFS, $this->sanitize_pdf_buttons($buttons));
        update_option(self::OPTION_PUBLIC_URL, $public_menu_url);
        update_option(self::OPTION_PDF_ASSETS, $this->sanitize_pdf_assets($pdf_assets));

        wp_safe_redirect($this->admin_url('saved'));
        exit;
    }

    public function handle_adjust_prices(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('No tienes permisos para editar la carta.');
        }

        check_admin_referer('mqd_adjust_prices');

        $percentage = isset($_POST['percentage']) ? (float) wp_unslash($_POST['percentage']) : 0.0;
        $enabled = isset($_POST['price_enabled']) && (string) wp_unslash($_POST['price_enabled']) === '1';

        update_option(
            self::OPTION_PRICE_SETTINGS,
            [
                'percentage' => $percentage,
                'enabled'    => $enabled,
            ]
        );

        wp_safe_redirect($this->admin_url('adjusted'));
        exit;
    }

    public function handle_preview_menu_pdf(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('No tienes permisos para previsualizar el PDF.');
        }

        check_admin_referer('menu_pdf_action');

        $this->generate_menu_pdf('preview');
    }

    public function handle_download_menu_pdf(): void
    {
        $this->generate_menu_pdf('download');
    }

    public function render_shortcode(): string
    {
        $menu_data = get_option(self::OPTION_MENU, self::default_menu());
        $pdf_buttons = get_option(self::OPTION_PDFS, self::default_pdf_buttons());
        $price_settings = $this->get_price_settings();

        ob_start();
        ?>
        <div class="mqd-menu">
            <div class="mqd-menu-header">
                <h2>Carta de comidas</h2>
            </div>

            <div class="mqd-menu-tools" aria-label="Herramientas de carta">
                <div class="mqd-menu-tools-row">
                    <label class="mqd-search-field">
                        <span class="screen-reader-text">Buscar en la carta</span>
                        <input type="search" class="mqd-search-input" placeholder="Buscar plato..." data-mqd-search>
                    </label>
                    <div class="mqd-actions-group">
                        <button type="button" class="mqd-tool-link" data-mqd-clear-search hidden>Limpiar</button>
                        <button type="button" class="mqd-tool-link" data-mqd-expand-all>Abrir todo</button>
                        <button type="button" class="mqd-tool-link" data-mqd-collapse-all>Cerrar todo</button>
                    </div>
                </div>
                <div class="mqd-menu-tools-row">
                    <div class="mqd-section-shortcuts" aria-label="Accesos rapidos">
                        <?php foreach ($menu_data as $index => $section) : ?>
                            <?php
                            $title = isset($section['title']) ? (string) $section['title'] : '';
                            $items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];
                            if ($title === '' && empty($items)) {
                                continue;
                            }
                            ?>
                            <button type="button" class="mqd-shortcut" data-mqd-jump="<?php echo esc_attr('mqd-section-' . $index); ?>">
                                <?php echo esc_html($title); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="mqd-results" data-mqd-results><?php echo esc_html((string) $this->count_menu_items($menu_data)); ?> platos</p>
                </div>
            </div>

            <div class="mqd-accordion">
                <?php foreach ($menu_data as $index => $section) : ?>
                    <?php
                    $title = isset($section['title']) ? (string) $section['title'] : '';
                    $items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];
                    if ($title === '' && empty($items)) {
                        continue;
                    }
                    $content_id = 'mqd-section-content-' . $index;
                    $item_count = count($items);
                    ?>
                    <section class="mqd-section" id="<?php echo esc_attr('mqd-section-' . $index); ?>" data-mqd-section-shell>
                        <button
                            type="button"
                            class="mqd-section-toggle"
                            aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo esc_attr($content_id); ?>"
                        >
                            <span class="mqd-section-title-wrap">
                                <span class="mqd-toggle-symbol"><?php echo $index === 0 ? '-' : '+'; ?></span>
                                <span><?php echo esc_html($title); ?></span>
                            </span>
                            <span class="mqd-section-count"><?php echo esc_html((string) $item_count); ?></span>
                        </button>
                        <div
                            id="<?php echo esc_attr($content_id); ?>"
                            class="mqd-section-content"
                            <?php echo $index === 0 ? '' : 'hidden'; ?>
                            data-mqd-section-content
                        >
                            <?php foreach ($items as $item) : ?>
                                <?php
                                if (! is_array($item)) {
                                    $item = [];
                                }
                                $display_price = $this->get_display_price((string) ($item['price'] ?? ''), $price_settings);
                                $search_text = trim(
                                    implode(
                                        ' ',
                                        array_filter([
                                            isset($item['name']) ? (string) $item['name'] : '',
                                            isset($item['english_name']) ? (string) $item['english_name'] : '',
                                            $display_price,
                                        ])
                                    )
                                );
                                ?>
                                <article class="mqd-item" data-mqd-item data-search-text="<?php echo esc_attr($this->normalize_search_text($search_text)); ?>">
                                    <div class="mqd-item-content">
                                        <span class="mqd-item-name-es" data-mqd-item-name><?php echo esc_html($item['name'] ?? ''); ?></span>
                                        <span class="mqd-item-price"><?php echo esc_html($display_price); ?></span>
                                    </div>
                                    <?php if (! empty($item['english_name'])) : ?>
                                        <div class="mqd-item-name-en" data-mqd-item-english><?php echo esc_html($item['english_name']); ?></div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <p class="mqd-empty-state" data-mqd-empty-state hidden>No hay resultados para esa busqueda.</p>

            <?php if (! empty($pdf_buttons)) : ?>
                <div class="mqd-downloads">
                    <?php foreach ($pdf_buttons as $button) : ?>
                        <?php if (empty($button['label']) || empty($button['url'])) { continue; } ?>
                        <a class="mqd-download-button" href="<?php echo esc_url($button['url']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($button['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="mqd-downloads">
                <a class="mqd-download-button" href="<?php echo esc_url(admin_url('admin-post.php?action=download_menu_pdf')); ?>">
                    Descargar PDF
                </a>
            </div>

            <button type="button" class="mqd-back-top" data-mqd-back-top hidden aria-label="Subir">&#8593;</button>
        </div>
        <?php

        $html = (string) ob_get_clean();
        $html = preg_replace('/>\s+</', '><', $html);

        return is_string($html) ? $html : '';
    }

    private function count_menu_items(array $menu_data): int
    {
        $count = 0;

        foreach ($menu_data as $section) {
            if (! empty($section['items']) && is_array($section['items'])) {
                $count += count($section['items']);
            }
        }

        return $count;
    }

    private function normalize_search_text(string $text): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (function_exists('remove_accents')) {
            $text = remove_accents($text);
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($text, 'UTF-8');
        }

        return strtolower($text);
    }

    private function admin_url(string $notice): string
    {
        return add_query_arg(
            [
                'page' => self::PAGE_SLUG,
                'mqd_notice' => $notice,
            ],
            admin_url('admin.php')
        );
    }

    private function build_qr_image_url(string $url, int $size = 320): string
    {
        return add_query_arg(
            [
                'size'   => absint($size) . 'x' . absint($size),
                'margin' => 16,
                'format' => 'svg',
                'data'   => $url,
            ],
            'https://api.qrserver.com/v1/create-qr-code/'
        );
    }

    private function generate_menu_pdf(string $mode = 'preview'): void
    {
        if (! class_exists(\Mpdf\Mpdf::class)) {
            status_header(500);
            wp_die('La libreria mPDF no esta disponible.');
        }

        try {
            $temp_dir = plugin_dir_path(__FILE__) . 'tmp';
            if (! file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 16,
                'margin_right' => 16,
                'margin_top' => 18,
                'margin_bottom' => 18,
                'margin_header' => 0,
                'margin_footer' => 0,
                'tempDir' => $temp_dir,
            ]);

            $mpdf->SetTitle('Carta Restaurante');
            $mpdf->SetAuthor('Menu QR Dinamico');
            $mpdf->SetDisplayMode('fullwidth');
            $document = $this->get_menu_pdf_document_data();
            $pdf_assets = $this->get_pdf_assets();

            if ($pdf_assets['cover_image'] !== '') {
                $mpdf->WriteHTML($this->get_menu_pdf_cover_html($pdf_assets['cover_image']));
            }

            if ($pdf_assets['background_image'] !== '') {
                $mpdf->SetWatermarkImage($pdf_assets['background_image'], 1, '210mm,297mm', [0, 0]);
                $mpdf->showWatermarkImage = true;
                $mpdf->watermarkImgBehind = true;
                $mpdf->watermarkImageAlpha = 1;
            }

            $mpdf->WriteHTML($this->get_menu_pdf_html($document, $pdf_assets));

            if ($mode === 'download') {
                $mpdf->Output($this->get_pdf_filename(), \Mpdf\Output\Destination::DOWNLOAD);
                exit;
            }

            $mpdf->Output($this->get_pdf_filename(), \Mpdf\Output\Destination::INLINE);
            exit;
        } catch (\Throwable $exception) {
            status_header(500);
            wp_die('No se ha podido generar el PDF en este momento.');
        }
    }

    private function get_menu_pdf_document_data(): array
    {
        $menu_data = get_option(self::OPTION_MENU, self::default_menu());
        $price_settings = $this->get_price_settings();
        $sections = [];

        foreach ($menu_data as $section) {
            if (! is_array($section)) {
                continue;
            }

            $title = isset($section['title']) ? (string) $section['title'] : '';
            $items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];

            if ($title === '' && empty($items)) {
                continue;
            }

            $prepared_items = [];
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name_parts = $this->split_pdf_label((string) ($item['name'] ?? ''));
                $english_parts = $this->split_pdf_label((string) ($item['english_name'] ?? ''));

                $prepared_items[] = [
                    'name' => $name_parts['main'],
                    'name_small' => $name_parts['small'],
                    'english_name' => $english_parts['main'],
                    'english_small' => $english_parts['small'],
                    'price' => $this->format_pdf_price(
                        $this->get_display_price((string) ($item['price'] ?? ''), $price_settings)
                    ),
                ];
            }

            if (empty($prepared_items)) {
                continue;
            }

            $sections[] = [
                'title' => $this->normalize_pdf_text($title),
                'title_en' => $this->normalize_pdf_text($this->get_section_translation($title)),
                'title_html' => $this->format_pdf_title_html($title),
                'title_en_html' => $this->format_pdf_translation_html($title),
                'items' => $prepared_items,
            ];
        }

        return [
            'document_title' => 'Carta de comidas',
            'sections' => $sections,
            'seafood_note' => [
                'es' => 'Nuestros mariscos están basados en el mercado del día.<br>Consulte tanto su disponibilidad como su precio en nuestras pizarras.',
                'en' => 'Our seafood is based on the market of the day. Check both their availability<br>and their price on our blackboards.',
            ],
            'person_note' => [
                'es' => '*Precio por persona',
                'en' => '*Price per person',
            ],
            'fish_note' => [
                'es' => 'Todos nuestros pescados son de Ría o Mar.<br>Consulte tanto su disponibilidad como precio en nuestras pizarras.',
                'en' => 'All our fish are from the estuary or the sea.<br>Check availability and price on our blackboards.',
            ],
        ];
    }

    private function get_menu_pdf_html(array $document, array $pdf_assets): string
    {
        $document_title = $document['document_title'];
        $sections = $document['sections'];
        $seafood_note = $document['seafood_note'];
        $person_note = $document['person_note'];
        $fish_note = $document['fish_note'];
        $background_image = $pdf_assets['background_image'];
        $template_path = plugin_dir_path(__FILE__) . 'templates/pdf-menu.php';

        if (! file_exists($template_path)) {
            return '<h1>Carta</h1>';
        }

        ob_start();
        include $template_path;
        return (string) ob_get_clean();
    }

    private function get_menu_pdf_cover_html(string $cover_image): string
    {
        $cover_image = esc_url_raw($cover_image);

        return '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:0;}body{margin:0;padding:0;background:#fff;}img{display:block;width:210mm;height:297mm;object-fit:cover;}.cover-page{page-break-after:always;}</style></head><body><div class="cover-page"><img src="' . esc_attr($cover_image) . '" alt=""></div></body></html>';
    }

    private function get_section_translation(string $title): string
    {
        $key = $this->normalize_search_text($title);
        $translations = [
            'entrantes' => 'Starters',
            'nuestros mariscos del dia' => 'Our Seafoods from the Ria',
            'ensaladas' => 'Salads',
            'pescados' => 'Fishes',
            'carnes' => 'Meats',
            'arroces' => 'Rices',
            'postres' => 'Desserts',
            'bebidas' => 'Drinks',
        ];

        return $translations[$key] ?? '';
    }

    private function normalize_pdf_text(string $text): string
    {
        $text = str_replace(['â‚¬', "\r\n", "\r"], ['€', "\n", "\n"], $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace("/[ \t]+/", ' ', $text);
        return trim((string) $text);
    }

    private function get_pdf_filename(): string
    {
        return 'carta-restaurante-' . gmdate('Ymd-His') . '.pdf';
    }

    private function get_pdf_assets(): array
    {
        $assets = get_option(self::OPTION_PDF_ASSETS, self::default_pdf_assets());

        if (! is_array($assets)) {
            return self::default_pdf_assets();
        }

        return [
            'cover_image' => isset($assets['cover_image']) ? esc_url_raw((string) $assets['cover_image']) : '',
            'background_image' => isset($assets['background_image']) ? esc_url_raw((string) $assets['background_image']) : '',
        ];
    }

    private function sanitize_pdf_assets($assets): array
    {
        if (! is_array($assets)) {
            return self::default_pdf_assets();
        }

        return [
            'cover_image' => isset($assets['cover_image']) ? esc_url_raw((string) $assets['cover_image']) : '',
            'background_image' => isset($assets['background_image']) ? esc_url_raw((string) $assets['background_image']) : '',
        ];
    }

    private function format_pdf_price(string $price): string
    {
        $price = $this->normalize_pdf_text($price);

        if ($price === '' || stripos($price, 's/m') !== false) {
            return $price;
        }

        if (! preg_match('/(\d+(?:[.,]\d+)?)/', $price, $matches)) {
            return $price;
        }

        $amount = (float) str_replace(',', '.', $matches[1]);
        return number_format($amount, 2, ',', '.') . ' €';
    }

    private function format_pdf_title_html(string $title): string
    {
        $clean = $this->normalize_pdf_text($title);

        if ($this->normalize_search_text($clean) === 'nuestros mariscos del dia') {
            return 'Nuestros<br>Mariscos<br>del Dia';
        }

        return esc_html($clean);
    }

    private function format_pdf_translation_html(string $title): string
    {
        if ($this->normalize_search_text($title) === 'nuestros mariscos del dia') {
            return 'Our<br>Seafoods<br>from the Ria';
        }

        return esc_html($this->normalize_pdf_text($this->get_section_translation($title)));
    }

    private function split_pdf_label(string $text): array
    {
        $text = $this->normalize_pdf_text($text);

        if ($text === '') {
            return ['main' => '', 'small' => ''];
        }

        if (preg_match('/^(.*?)\s*(\([^)]*\))$/u', $text, $matches)) {
            return [
                'main' => trim($matches[1]),
                'small' => trim($matches[2]),
            ];
        }

        return [
            'main' => $text,
            'small' => '',
        ];
    }

    private function render_section_fields(int $index, array $section): void
    {
        $title = isset($section['title']) ? (string) $section['title'] : '';
        $items = isset($section['items']) && is_array($section['items']) ? $section['items'] : [];
        ?>
        <div class="mqd-admin-section" data-mqd-section>
            <div class="mqd-admin-section-head">
                <button type="button" class="mqd-collapse-toggle" data-mqd-collapse-toggle>
                    <span class="mqd-collapse-symbol">-</span>
                    <span class="mqd-section-title-preview"><?php echo esc_html($title ?: 'Nueva seccion'); ?></span>
                </button>
                <button type="button" class="button-link-delete" data-mqd-remove-section>Eliminar</button>
            </div>
            <div class="mqd-admin-section-body" data-mqd-collapse-body>
                <label class="mqd-field">
                    <span>Titulo de la seccion</span>
                    <input type="text" name="sections[<?php echo esc_attr((string) $index); ?>][title]" value="<?php echo esc_attr($title); ?>" data-mqd-section-title>
                </label>
                <div class="mqd-items" data-mqd-items>
                    <?php foreach ($items as $item_index => $item) : ?>
                        <?php $this->render_item_fields($index, $item_index, $item); ?>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" data-mqd-add-item>Anadir plato</button>
            </div>
        </div>
        <?php
    }

    private function render_item_fields(int $section_index, int $item_index, array $item): void
    {
        $name = isset($item['name']) ? (string) $item['name'] : '';
        $price = isset($item['price']) ? (string) $item['price'] : '';
        $english_name = isset($item['english_name']) ? (string) $item['english_name'] : '';
        ?>
        <div class="mqd-admin-item" data-mqd-item>
            <div class="mqd-item-grid">
                <label class="mqd-field">
                    <span>Nombre</span>
                    <input type="text" name="sections[<?php echo esc_attr((string) $section_index); ?>][items][<?php echo esc_attr((string) $item_index); ?>][name]" value="<?php echo esc_attr($name); ?>">
                </label>
                <label class="mqd-field">
                    <span>Precio</span>
                    <input type="text" name="sections[<?php echo esc_attr((string) $section_index); ?>][items][<?php echo esc_attr((string) $item_index); ?>][price]" value="<?php echo esc_attr($price); ?>" placeholder="Ej: 12€ o S/M">
                </label>
                <label class="mqd-field mqd-field-full">
                    <span>Titulo en ingles</span>
                    <input type="text" name="sections[<?php echo esc_attr((string) $section_index); ?>][items][<?php echo esc_attr((string) $item_index); ?>][english_name]" value="<?php echo esc_attr($english_name); ?>" placeholder="Ej: Anchovies with tetilla">
                </label>
            </div>
            <button type="button" class="button-link-delete" data-mqd-remove-item>Eliminar plato</button>
        </div>
        <?php
    }

    private function render_pdf_button_fields(int $index, array $button): void
    {
        $label = isset($button['label']) ? (string) $button['label'] : '';
        $url = isset($button['url']) ? (string) $button['url'] : '';
        ?>
        <div class="mqd-pdf-row" data-mqd-button>
            <label class="mqd-field">
                <span>Texto del boton</span>
                <input type="text" name="pdf_buttons[<?php echo esc_attr((string) $index); ?>][label]" value="<?php echo esc_attr($label); ?>">
            </label>
            <label class="mqd-field mqd-pdf-url">
                <span>URL del PDF</span>
                <input type="url" name="pdf_buttons[<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr($url); ?>">
            </label>
            <div class="mqd-pdf-actions">
                <button type="button" class="button" data-mqd-pick-pdf>Subir / elegir PDF</button>
                <button type="button" class="button-link-delete" data-mqd-remove-button>Eliminar</button>
            </div>
        </div>
        <?php
    }

    private function sanitize_sections($sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        $clean_sections = [];

        foreach ($sections as $section) {
            $title = isset($section['title']) ? sanitize_text_field((string) $section['title']) : '';
            $items = [];

            if (! empty($section['items']) && is_array($section['items'])) {
                foreach ($section['items'] as $item) {
                    $name = isset($item['name']) ? sanitize_text_field((string) $item['name']) : '';
                    $price = isset($item['price']) ? sanitize_text_field((string) $item['price']) : '';
                    $english_name = isset($item['english_name']) ? sanitize_text_field((string) $item['english_name']) : '';

                    if ($name === '' && $price === '' && $english_name === '') {
                        continue;
                    }

                    $items[] = [
                        'name'         => $name,
                        'price'        => $price,
                        'english_name' => $english_name,
                    ];
                }
            }

            if ($title === '' && empty($items)) {
                continue;
            }

            $clean_sections[] = [
                'title' => $title,
                'items' => $items,
            ];
        }

        return $clean_sections;
    }

    private function sanitize_pdf_buttons($buttons): array
    {
        if (! is_array($buttons)) {
            return [];
        }

        $clean_buttons = [];

        foreach ($buttons as $button) {
            $label = isset($button['label']) ? sanitize_text_field((string) $button['label']) : '';
            $url = isset($button['url']) ? esc_url_raw((string) $button['url']) : '';

            if ($label === '' && $url === '') {
                continue;
            }

            $clean_buttons[] = [
                'label' => $label,
                'url'   => $url,
            ];
        }

        return $clean_buttons;
    }

    private function adjust_price(string $price, float $percentage): string
    {
        $normalized = trim($price);

        if ($normalized === '' || stripos($normalized, 's/m') !== false) {
            return $price;
        }

        if (! preg_match('/(\d+(?:[.,]\d+)?)/', $normalized, $matches)) {
            return $price;
        }

        $base = (float) str_replace(',', '.', $matches[1]);
        $adjusted = $base + ($base * ($percentage / 100));
        $formatted = floor($adjusted) == $adjusted
            ? number_format($adjusted, 0, ',', '.')
            : number_format($adjusted, 2, ',', '.');

        return $formatted . '€';
    }

    private function get_display_price(string $price, array $price_settings): string
    {
        if (empty($price_settings['enabled'])) {
            return $price;
        }

        return $this->adjust_price($price, (float) ($price_settings['percentage'] ?? 0));
    }

    private function get_price_settings(): array
    {
        $settings = get_option(self::OPTION_PRICE_SETTINGS, self::default_price_settings());

        if (! is_array($settings)) {
            return self::default_price_settings();
        }

        return [
            'percentage' => isset($settings['percentage']) ? (float) $settings['percentage'] : 10.0,
            'enabled'    => ! empty($settings['enabled']),
        ];
    }

    private function maybe_upgrade(): void
    {
        $installed_version = get_option(self::OPTION_VERSION, '1.0.0');

        if (version_compare((string) $installed_version, '1.4.0', '>=')) {
            return;
        }

        $menu_data = get_option(self::OPTION_MENU, []);
        $default_menu = self::default_menu();

        if (is_array($menu_data) && ! empty($menu_data)) {
            foreach ($menu_data as $section_index => &$section) {
                if (empty($section['items']) || ! is_array($section['items'])) {
                    continue;
                }

                $existing_names = [];
                foreach ($section['items'] as $item_index => &$item) {
                    $existing_name = isset($item['name']) ? (string) $item['name'] : '';
                    if ($existing_name !== '') {
                        $existing_names[] = $existing_name;
                    }

                    if (! empty($item['english_name'])) {
                        continue;
                    }

                    $default_translation = $default_menu[$section_index]['items'][$item_index]['english_name'] ?? '';
                    if ($default_translation !== '') {
                        $item['english_name'] = $default_translation;
                    }
                }
                unset($item);

                $default_items = $default_menu[$section_index]['items'] ?? [];
                foreach ($default_items as $default_item) {
                    $default_name = isset($default_item['name']) ? (string) $default_item['name'] : '';
                    if ($default_name === '' || in_array($default_name, $existing_names, true)) {
                        continue;
                    }

                    $section['items'][] = $default_item;
                }
            }
            unset($section);

            update_option(self::OPTION_MENU, $menu_data);
        }

        if (get_option(self::OPTION_PUBLIC_URL, null) === null) {
            add_option(self::OPTION_PUBLIC_URL, '');
        }

        if (get_option(self::OPTION_PRICE_SETTINGS, null) === null) {
            add_option(self::OPTION_PRICE_SETTINGS, self::default_price_settings());
        }

        if (get_option(self::OPTION_PDF_ASSETS, null) === null) {
            add_option(self::OPTION_PDF_ASSETS, self::default_pdf_assets());
        }

        update_option(self::OPTION_VERSION, '1.4.0');
    }

    private function get_section_template(): string
    {
        ob_start();
        $this->render_section_fields(999999, ['title' => '', 'items' => []]);
        return (string) ob_get_clean();
    }

    private function get_item_template(): string
    {
        ob_start();
        $this->render_item_fields(999999, 999999, ['name' => '', 'price' => '', 'english_name' => '']);
        return (string) ob_get_clean();
    }

    private function get_pdf_button_template(): string
    {
        ob_start();
        $this->render_pdf_button_fields(999999, ['label' => '', 'url' => '']);
        return (string) ob_get_clean();
    }

    private static function default_pdf_buttons(): array
    {
        return [
            ['label' => 'Descargar Carta Comida', 'url' => ''],
            ['label' => 'Descargar Carta Postres', 'url' => ''],
        ];
    }

    private static function default_price_settings(): array
    {
        return [
            'percentage' => 10.0,
            'enabled'    => false,
        ];
    }

    private static function default_pdf_assets(): array
    {
        return [
            'cover_image' => '',
            'background_image' => '',
        ];
    }

    private static function default_menu(): array
    {
        return [
            [
                'title' => 'Entrantes',
                'items' => [
                    ['name' => 'Anchoas con tetilla', 'price' => '28€', 'english_name' => 'Anchovies with tetilla'],
                    ['name' => 'Empanada Casera (Consulte a nuestro personal de qué es hoy)', 'price' => '12€', 'english_name' => 'Homemade Pie (Ask our staff what it is today)'],
                    ['name' => 'Croquetas de Carabinero', 'price' => '15€', 'english_name' => 'King Prawn Croquettes'],
                    ['name' => 'Ensaladilla Rusa', 'price' => '12€', 'english_name' => 'Russian Salad'],
                    ['name' => 'Pulpo Feira o Plancha', 'price' => '23€', 'english_name' => 'Octopus Feira or Grilled'],
                    ['name' => 'Calamar encebollado / romana', 'price' => '15€', 'english_name' => 'Squid in onion / romaine'],
                    ['name' => 'Revuelto de langostino, setas y jamón', 'price' => '14€', 'english_name' => 'Scrambled eggs with prawns, mushrooms and ham'],
                    ['name' => 'Jamón Ibérico', 'price' => '22€', 'english_name' => 'Iberian Ham'],
                    ['name' => 'Queso de Cabra', 'price' => '14€', 'english_name' => 'Goat Cheese'],
                    ['name' => 'Pimiento de Padrón (en temporada)', 'price' => '10€', 'english_name' => 'Padron Pepper (in season)'],
                ],
            ],
            [
                'title' => 'Nuestros Mariscos del Día',
                'items' => [
                    ['name' => 'Navajas a la plancha', 'price' => 'S/M', 'english_name' => 'Grilled razor clams'],
                    ['name' => 'Berberechos al vapor', 'price' => 'S/M', 'english_name' => 'Steamed cockles'],
                    ['name' => 'Almejas a la Marinera', 'price' => 'S/M', 'english_name' => 'Clams a la Marinera'],
                    ['name' => 'Zamburiña', 'price' => '20€', 'english_name' => 'Scallop'],
                    ['name' => 'Camarón', 'price' => 'S/M', 'english_name' => 'Shrimp'],
                    ['name' => 'Bogavante', 'price' => 'S/M', 'english_name' => 'Spiny Lobster'],
                    ['name' => 'Percebe', 'price' => 'S/M', 'english_name' => 'Barnacle'],
                    ['name' => 'Cigala', 'price' => 'S/M', 'english_name' => 'Crayfish'],
                    ['name' => 'Langosta', 'price' => 'S/M', 'english_name' => 'Clawed Lobster'],
                    ['name' => 'Centolla', 'price' => 'S/M', 'english_name' => 'Spider crab'],
                    ['name' => 'Bruño', 'price' => 'S/M', 'english_name' => 'Bruño'],
                    ['name' => 'Nécora', 'price' => 'S/M', 'english_name' => 'Crab'],
                ],
            ],
            [
                'title' => 'Ensaladas',
                'items' => [
                    ['name' => 'Ensalada simple', 'price' => '8€', 'english_name' => 'Simple salad'],
                    ['name' => 'Ensalada de la casa', 'price' => '13€', 'english_name' => 'House salad'],
                ],
            ],
            [
                'title' => 'Pescados',
                'items' => [
                    ['name' => 'Lenguado', 'price' => 'S/M', 'english_name' => 'Sole'],
                    ['name' => 'Rodaballo', 'price' => 'S/M', 'english_name' => 'Turbot'],
                    ['name' => 'Corujo', 'price' => 'S/M', 'english_name' => 'Corujo'],
                    ['name' => 'Lubina', 'price' => 'S/M', 'english_name' => 'Sea bass'],
                    ['name' => 'Mero', 'price' => 'S/M', 'english_name' => 'Grouper'],
                    ['name' => 'Sargo', 'price' => 'S/M', 'english_name' => 'Bream'],
                    ['name' => 'Besugo', 'price' => 'S/M', 'english_name' => 'Sea bream'],
                    ['name' => 'Palometa Roja', 'price' => 'S/M', 'english_name' => 'Red pomfret'],
                    ['name' => 'Martiño', 'price' => 'S/M', 'english_name' => 'Martiño'],
                    ['name' => 'Dorada', 'price' => 'S/M', 'english_name' => 'Gold bream'],
                    ['name' => 'Brocheta de Rape y Langostinos', 'price' => '28€', 'english_name' => 'Monkfish and prawn skewers'],
                ],
            ],
            [
                'title' => 'Carnes',
                'items' => [
                    ['name' => 'Solomillo de Ternera gallega', 'price' => '24€', 'english_name' => 'Galician beef tenderloin'],
                ],
            ],
            [
                'title' => 'Arroces',
                'items' => [
                    ['name' => 'Arroz de marisco', 'price' => '27€', 'english_name' => 'Seafood rice'],
                    ['name' => 'Arroz de rape y carabinero', 'price' => '39€', 'english_name' => 'Monkfish and carabinero rice'],
                    ['name' => 'Arroz de bogavante (precio según el peso del bogavante)', 'price' => 'S/M', 'english_name' => 'Lobster rice (price according to the weight of the lobster)'],
                ],
            ],
        ];
    }
}

register_activation_hook(__FILE__, ['Menu_QR_Dinamico', 'activate']);
Menu_QR_Dinamico::boot();
