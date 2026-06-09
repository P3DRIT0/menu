# Menu QR Dinamico

Plugin de WordPress para mostrar una carta editable desde un unico QR.

## Que hace

- Muestra la carta con secciones plegables.
- Permite editar platos, precios y textos desde el panel de WordPress.
- Aplica una subida o bajada global de precios en porcentaje.
- Mantiene sin cambios los precios marcados como `S/M`.
- Anade botones para descargar PDFs asociados a la carta.

## Instalacion

1. Copia la carpeta `menu-qr-dinamico` dentro de `wp-content/plugins/`.
2. Activa el plugin desde WordPress.
3. En el panel veras el menu `Carta dinamica`.
4. Crea o edita una pagina y usa el shortcode:

```txt
[menu_qr_dinamico]
```

## Uso rapido

- En `Carta dinamica` puedes plegar cada seccion para editarla mejor.
- El boton `Anadir seccion` crea nuevas categorias.
- El boton `Anadir plato` crea nuevas lineas dentro de cada categoria.
- En `Botones PDF` puedes subir los archivos y enlazarlos a los botones inferiores.
- En `Subida global de precios` puedes poner `10` para subir toda la carta un 10%.
