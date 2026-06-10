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
            size: A4;
            margin: 18mm 14mm 18mm 14mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #756f5e;
            font-family: dejavusans, sans-serif;
            font-weight: 300;
        }

        body {
            width: 100%;
        }

        .amares-menu {
            width: 100%;
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #756f5e;
        }

        .amares-section {
            width: 100%;
            margin: 0 0 15mm 0;
            page-break-inside: auto;
            clear: both;
        }

        .amares-section:after {
            content: "";
            display: block;
            clear: both;
        }

        .amares-category-wrap {
            float: left;
            width: 30mm;
            padding-right: 5mm;
        }

        .amares-category {
            color: #756f5e;
            font-size: 13.5pt;
            line-height: 1.24;
            font-weight: 400;
        }

        .amares-category span {
            display: block;
        }

        .amares-category em {
            display: block;
            margin-top: 2mm;
            font-size: 13.5pt;
            line-height: 1.18;
            font-style: italic;
            font-weight: 300;
        }

        .amares-items {
            margin-left: 35mm;
            width: auto;
        }

        .amares-item {
            width: 100%;
            margin: 0 0 4.5mm 0;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .amares-item tr {
            page-break-inside: avoid;
        }

        .amares-item-copy {
            width: auto;
            padding-right: 5mm;
            vertical-align: top;
        }

        .amares-item-price-wrap {
            width: 24mm;
            min-width: 24mm;
            max-width: 24mm;
            padding: 0;
            vertical-align: top;
            text-align: right;
            white-space: nowrap;
        }

        .amares-item h3 {
            margin: 0;
            color: #565656;
            font-size: 12.8pt;
            line-height: 1.08;
            font-weight: 300;
            letter-spacing: -0.2pt;
        }

        .amares-item h3 small {
            font-size: 9.5pt;
            font-style: italic;
            font-weight: 300;
        }

        .amares-item p {
            margin: 0.8mm 0 0;
            color: #7c7c72;
            font-size: 9.4pt;
            line-height: 1.08;
            font-style: italic;
            font-weight: 300;
        }

        .amares-item p small {
            font-size: 8.5pt;
        }

        .amares-item strong {
            display: block;
            color: #756f5e;
            font-size: 12pt;
            line-height: 1.05;
            font-weight: 400;
            text-align: right;
            white-space: nowrap;
        }

        .amares-note {
            margin: 0 auto 14mm;
            text-align: center;
            color: #746f63;
            font-size: 10pt;
            line-height: 1.4;
            page-break-inside: avoid;
            clear: both;
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
            font-size: 9pt;
            line-height: 1.2;
            margin: 0 0 10mm;
            page-break-inside: avoid;
            clear: both;
        }

        .amares-person-note p {
            margin: 0;
        }

        .amares-person-note em {
            font-style: italic;
        }

        .amares-section,
        .amares-item,
        .amares-note,
        .amares-person-note {
            overflow: hidden;
        }
    </style>
</head>

<body>

<section class="amares-menu">

    <?php foreach ($sections as $index => $section) : ?>

        <div class="amares-section">

            <div class="amares-category-wrap">
                <div class="amares-category">
                    <span>
                        <?php echo $section['title_html']; ?>
                    </span>

                    <em>
                        <?php echo $section['title_en_html']; ?>
                    </em>
                </div>
            </div>

            <div class="amares-items">

                <?php foreach ($section['items'] as $item) : ?>

                    <table class="amares-item">

                        <tr>

                            <td class="amares-item-copy">

                                <h3>
                                    <?php echo esc_html($item['name']); ?>

                                    <?php if ($item['name_small'] !== '') : ?>
                                        <small>
                                            <?php echo esc_html($item['name_small']); ?>
                                        </small>
                                    <?php endif; ?>
                                </h3>

                                <?php if (
                                    $item['english_name'] !== ''
                                    || $item['english_small'] !== ''
                                ) : ?>

                                    <p>
                                        <?php echo esc_html($item['english_name']); ?>

                                        <?php if ($item['english_small'] !== '') : ?>
                                            <small>
                                                <?php echo esc_html($item['english_small']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </p>

                                <?php endif; ?>

                            </td>

                            <td class="amares-item-price-wrap">
                                <strong>
                                    <?php echo esc_html($item['price']); ?>
                                </strong>
                            </td>

                        </tr>

                    </table>

                <?php endforeach; ?>

            </div>

        </div>

        <?php if ($index === 1) : ?>

            <div class="amares-note">

                <p>
                    <?php echo wp_kses($seafood_note['es'], ['br' => []]); ?>
                </p>

                <p>
                    <em>
                        <?php echo wp_kses($seafood_note['en'], ['br' => []]); ?>
                    </em>
                </p>

            </div>

        <?php endif; ?>

        <?php if ($section['title'] === 'Arroces') : ?>

            <div class="amares-person-note">

                <p>
                    <?php echo esc_html($person_note['es']); ?>

                    <br>

                    <em>
                        <?php echo esc_html($person_note['en']); ?>
                    </em>
                </p>

            </div>

        <?php endif; ?>

    <?php endforeach; ?>

    <div class="amares-note">

        <p>
            <?php echo wp_kses($fish_note['es'], ['br' => []]); ?>
        </p>

        <p>
            <em>
                <?php echo wp_kses($fish_note['en'], ['br' => []]); ?>
            </em>
        </p>

    </div>

</section>

</body>
</html>