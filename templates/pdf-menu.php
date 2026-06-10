<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 18mm 14mm 18mm 14mm;
        }

        body {
            margin: 0;
            padding: 0;
            background: transparent;
            color: #756f5e;
            font-family: dejavusans, sans-serif;
            font-weight: 300;
        }

        .amares-menu {
            width: 100%;
            margin: 0 auto;
            padding: 6mm 2mm 4mm;
            background: transparent;
            color: #756f5e;
        }

        .amares-section {
            width: 100%;
            margin: 0 0 16mm 0;
            page-break-inside: auto;
        }

        .amares-section:after {
            content: "";
            display: block;
            clear: both;
        }

        .amares-category-wrap {
            float: left;
            width: 34mm;
            padding: 0 7mm 0 0;
        }

        .amares-category {
            color: #756f5e;
            font-size: 15pt;
            line-height: 1.26;
            font-weight: 400;
        }

        .amares-category span {
            display: block;
        }

        .amares-category em {
            display: block;
            margin-top: 3mm;
            font-size: 15pt;
            line-height: 1.22;
            font-style: italic;
            font-weight: 300;
        }

        .amares-items {
            margin-left: 41mm;
            width: auto;
        }

        .amares-item {
            width: 100%;
            margin: 0 0 5mm 0;
            page-break-inside: avoid;
        }

        .amares-item-copy {
            width: 132mm;
            padding: 0 4mm 0 0;
            vertical-align: top;
        }

        .amares-item-price-wrap {
            width: 24mm;
            padding: 0;
            vertical-align: top;
            text-align: right;
            white-space: nowrap;
        }

        .amares-item h3 {
            margin: 0;
            color: #565656;
            font-size: 15.5pt;
            line-height: 1.05;
            font-weight: 300;
            letter-spacing: -0.25pt;
        }

        .amares-item h3 small {
            font-size: 11pt;
            font-style: italic;
            font-weight: 300;
        }

        .amares-item p {
            margin: 1.2mm 0 0;
            color: #7c7c72;
            font-size: 10.8pt;
            line-height: 1.08;
            font-style: italic;
            font-weight: 300;
        }

        .amares-item p small {
            font-size: 9.5pt;
        }

        .amares-item strong {
            color: #756f5e;
            font-size: 13.2pt;
            line-height: 1;
            font-weight: 400;
            text-align: right;
            white-space: nowrap;
        }

        .amares-note {
            margin: -4mm auto 16mm;
            text-align: center;
            color: #746f63;
            font-size: 10.8pt;
            line-height: 1.45;
            page-break-inside: avoid;
        }

        .amares-note p {
            margin: 0 0 3mm;
        }

        .amares-note em {
            font-style: italic;
        }

        .amares-person-note {
            text-align: right;
            color: #756f5e;
            font-size: 9.5pt;
            line-height: 1.2;
            margin-top: -8mm;
            margin-bottom: 10mm;
            page-break-inside: avoid;
        }

        .amares-person-note p {
            margin: 0;
        }

        .amares-person-note em {
            font-style: italic;
        }
    </style>
</head>
<body>
    <section class="amares-menu">
        <?php foreach ($sections as $index => $section) : ?>
            <div class="amares-section">
                <div class="amares-category-wrap">
                    <div class="amares-category">
                        <span><?php echo $section['title_html']; ?></span>
                        <em><?php echo $section['title_en_html']; ?></em>
                    </div>
                </div>

                <div class="amares-items">
                    <?php foreach ($section['items'] as $item) : ?>
                        <table class="amares-item" autosize="1">
                            <tr>
                                <td class="amares-item-copy">
                                    <h3>
                                        <?php echo esc_html($item['name']); ?>
                                        <?php if ($item['name_small'] !== '') : ?>
                                            <small><?php echo esc_html($item['name_small']); ?></small>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($item['english_name'] !== '' || $item['english_small'] !== '') : ?>
                                        <p>
                                            <?php echo esc_html($item['english_name']); ?>
                                            <?php if ($item['english_small'] !== '') : ?>
                                                <small><?php echo esc_html($item['english_small']); ?></small>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                                <td class="amares-item-price-wrap">
                                    <strong><?php echo esc_html($item['price']); ?></strong>
                                </td>
                            </tr>
                        </table>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($index === 1) : ?>
                <div class="amares-note">
                    <p><?php echo wp_kses($seafood_note['es'], ['br' => []]); ?></p>
                    <p><em><?php echo wp_kses($seafood_note['en'], ['br' => []]); ?></em></p>
                </div>
            <?php endif; ?>

            <?php if ($section['title'] === 'Arroces') : ?>
                <div class="amares-person-note">
                    <p><?php echo esc_html($person_note['es']); ?><br><em><?php echo esc_html($person_note['en']); ?></em></p>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="amares-note">
            <p><?php echo wp_kses($fish_note['es'], ['br' => []]); ?></p>
            <p><em><?php echo wp_kses($fish_note['en'], ['br' => []]); ?></em></p>
        </div>
    </section>
</body>
</html>
