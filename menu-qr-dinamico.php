<?php
/**
 * Plugin Name: Menu QR Dinamico
 * Description: Gestiona una carta dinamica con secciones plegables, ajuste global de precios y botones para PDFs.
 * Version: 1.2.0
 * Author: Codex
 * Text Domain: menu-qr-dinamico
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Menu_QR_Dinamico
{
    private const OPTION_MENU = 'mqd_menu_data';
    private const OPTION_PDFS = 'mqd_pdf_buttons';
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

        update_option(self::OPTION_VERSION, '1.2.0');
    }

    private function __construct()
    {
        $this->maybe_upgrade();
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets']);
        add_action('admin_post_mqd_save_menu', [$this, 'handle_save_menu']);
        add_action('admin_post_mqd_adjust_prices', [$this, 'handle_adjust_prices']);
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
        $notice = isset($_GET['mqd_notice']) ? sanitize_text_field(wp_unslash($_GET['mqd_notice'])) : '';
        ?>
        <div class="wrap mqd-admin-wrap">
            <h1>Carta dinamica</h1>
            <p>Gestiona la carta que vera el cliente desde un solo QR y actualiza precios en bloque cuando lo necesites.</p>

            <?php if ($notice === 'saved') : ?>
                <div class="notice notice-success is-dismissible"><p>Carta guardada correctamente.</p></div>
            <?php elseif ($notice === 'adjusted') : ?>
                <div class="notice notice-success is-dismissible"><p>Los precios numericos se han actualizado.</p></div>
            <?php endif; ?>

            <div class="mqd-admin-grid">
                <div class="mqd-panel">
                    <h2>Subida global de precios</h2>
                    <p>Aplica un porcentaje a toda la carta. Los valores como <strong>S/M</strong> no se tocan.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('mqd_adjust_prices'); ?>
                        <input type="hidden" name="action" value="mqd_adjust_prices">
                        <div class="mqd-inline-fields">
                            <label for="mqd_percentage">Porcentaje</label>
                            <input id="mqd_percentage" type="number" name="percentage" step="0.01" value="10" required>
                            <button type="submit" class="button button-primary">Aplicar a toda la carta</button>
                        </div>
                    </form>
                </div>

                <div class="mqd-panel">
                    <h2>Shortcode</h2>
                    <p>Inserta <code>[menu_qr_dinamico]</code> en la pagina donde quieras mostrar la carta.</p>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="mqd-main-form">
                <?php wp_nonce_field('mqd_save_menu'); ?>
                <input type="hidden" name="action" value="mqd_save_menu">

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

        update_option(self::OPTION_MENU, $this->sanitize_sections($sections));
        update_option(self::OPTION_PDFS, $this->sanitize_pdf_buttons($buttons));

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
        $menu_data = get_option(self::OPTION_MENU, self::default_menu());

        foreach ($menu_data as &$section) {
            if (empty($section['items']) || ! is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as &$item) {
                $item['price'] = $this->adjust_price($item['price'] ?? '', $percentage);
            }
        }
        unset($item, $section);

        update_option(self::OPTION_MENU, $menu_data);

        wp_safe_redirect($this->admin_url('adjusted'));
        exit;
    }

    public function render_shortcode(): string
    {
        $menu_data = get_option(self::OPTION_MENU, self::default_menu());
        $pdf_buttons = get_option(self::OPTION_PDFS, self::default_pdf_buttons());

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
                                $search_text = trim(
                                    implode(
                                        ' ',
                                        array_filter([
                                            isset($item['name']) ? (string) $item['name'] : '',
                                            isset($item['english_name']) ? (string) $item['english_name'] : '',
                                            isset($item['price']) ? (string) $item['price'] : '',
                                        ])
                                    )
                                );
                                ?>
                                <article class="mqd-item" data-mqd-item data-search-text="<?php echo esc_attr($this->normalize_search_text($search_text)); ?>">
                                    <div class="mqd-item-content">
                                        <span class="mqd-item-name-es" data-mqd-item-name><?php echo esc_html($item['name'] ?? ''); ?></span>
                                        <span class="mqd-item-price"><?php echo esc_html($item['price'] ?? ''); ?></span>
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

            <button type="button" class="mqd-back-top" data-mqd-back-top hidden>Subir</button>
        </div>
        <?php

        return (string) ob_get_clean();
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

    private function maybe_upgrade(): void
    {
        $installed_version = get_option(self::OPTION_VERSION, '1.0.0');

        if (version_compare((string) $installed_version, '1.2.0', '>=')) {
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

        update_option(self::OPTION_VERSION, '1.2.0');
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
