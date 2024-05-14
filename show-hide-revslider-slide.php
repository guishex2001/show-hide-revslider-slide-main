<?php
/*
Plugin Name: Show Hide RevSlider Slide
Description: Plugin para mostrar y ocultar un slide específico en Revolution Slider.
Version: 1.0
Author: guishex2001
*/

// Función para activar el plugin
function activar_plugin() {
    // Aquí puedes realizar cualquier configuración necesaria al activar el plugin
}

// Función para desactivar el plugin
function desactivar_plugin() {
    // Aquí puedes realizar cualquier limpieza necesaria al desactivar el plugin
}

// Función para borrar el plugin
function borrar_plugin() {
    // Aquí puedes realizar cualquier limpieza necesaria al borrar el plugin
}

// Registrando los hooks de activación, desactivación y desinstalación del plugin
register_activation_hook(__FILE__, 'activar_plugin');
register_deactivation_hook(__FILE__, 'desactivar_plugin');
register_uninstall_hook(__FILE__, 'borrar_plugin');

// Agregar el menú de administración
add_action('admin_menu', 'crear_menu');

function crear_menu() {
    add_menu_page(
        'Show Hide',
        'Show Hide',
        'manage_options',
        'sh_menu',
        'mostrar_contenido',
        plugin_dir_url(__FILE__) . 'admin/img/icon.png',
        1
    );
}


// Función para mostrar el contenido de la página del menú
function mostrar_contenido() {
    global $wpdb;

    // Obtener todos los sliders de Revolution Slider
    $sliders = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}revslider_sliders");

    // Verificar si se ha enviado el formulario y si 'slide' está definido en $_POST
    if (isset($_POST['submit']) && isset($_POST['slide']) && isset($_POST['slider'])) {
        // Obtener los valores seleccionados del formulario
        $slider = $_POST['slider'];
        $slide = $_POST['slide'];

        // Determinar el estado actual del slide
        $estado_actual = obtener_estado_slide($wpdb, $slider, $slide);

        // Cambiar el estado del slide
        if ($estado_actual === 'visible') {
            ocultar_slide($wpdb, $slider, $slide);
        } else {
            mostrar_slide($wpdb, $slider, $slide);
        }
    }

    ?>
    <div class="show-hide-info">
        <h1>Show Hide RevSlider Slide</h1>
        <form method="post">
            <label for="slider">Selecciona el slider:</label>
            <select name="slider" id="slider">
                <option value="">Seleccionar slider</option>
                <?php foreach ($sliders as $s) : ?>
                    <option value="<?php echo $s->id; ?>"><?php echo $s->title; ?></option>
                <?php endforeach; ?>
            </select>
            <br>
            <label for="slide">Selecciona el slide:</label>
            <select name="slide" id="slide">
                <!-- Aquí se cargarán dinámicamente los slides del slider seleccionado -->
            </select>
            <br>
            <input type="submit" name="submit" value="Mostrar/Ocultar">
        </form>
    </div>
    <style>
        .show-hide-info {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 20px auto;
        }

        .show-hide-info h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .show-hide-info label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .show-hide-info select, .show-hide-info input[type="submit"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .show-hide-info input[type="submit"] {
            background: #0073aa;
            color: #fff;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            font-weight: bold;
        }

        .show-hide-info input[type="submit"]:hover {
            background: #005177;
        }
    </style>
    <script>
        // Función para cargar dinámicamente los slides cuando se seleccione un slider
        jQuery(document).ready(function ($) {
            $('#slider').change(function () {
                var slider_id = $(this).val();
                $.ajax({
                    url: ajaxurl, // Esta variable global de WordPress contiene la URL del archivo admin-ajax.php
                    type: 'post',
                    data: {
                        action: 'cargar_slides',
                        slider_id: slider_id
                    },
                    success: function (response) {
                        $('#slide').html(response);
                    }
                });
            });
        });
    </script>
    <?php
}
// Función para cargar dinámicamente los slides de un slider específico
add_action('wp_ajax_cargar_slides', 'cargar_slides_callback');
function cargar_slides_callback() {
    global $wpdb;
    $slider_id = $_POST['slider_id'];
    // Modificar la consulta para filtrar solo los slides que contengan "video" en el título
    $slides = $wpdb->get_results($wpdb->prepare("SELECT slide_order, params FROM {$wpdb->prefix}revslider_slides WHERE slider_id = %s AND params LIKE '%%\"title\":\"%video%\"%%'", $slider_id));

    $options_html = '';
    foreach ($slides as $slide) {
        $params = json_decode($slide->params);
        $slide_title = isset($params->title) ? $params->title : 'Slide ' . $slide->slide_order;
        $estado_actual = obtener_estado_slide($wpdb, $slider_id, $slide->slide_order);
        $options_html .= "<option value='{$slide->slide_order}'>{$slide_title} ({$estado_actual})</option>";
    }

    echo $options_html;
    exit;
}

// Función para obtener el estado actual del slide
function obtener_estado_slide($wpdb, $slider, $slide) {
    $table_name = $wpdb->prefix . 'revslider_slides';
    $query = $wpdb->prepare("SELECT params FROM $table_name WHERE slider_id = %s AND slide_order = %s", $slider, $slide);
    $params = $wpdb->get_var($query);

    // Verificar el estado del slide en los parámetros
    $params_array = json_decode($params, true);
    if (isset($params_array['publish']['state']) && $params_array['publish']['state'] === 'unpublished') {
        return 'oculto';
    } else {
        return 'visible';
    }
}

// Función para mostrar un slide
function mostrar_slide($wpdb, $slider, $slide) {
    $table_name = $wpdb->prefix . 'revslider_slides';
    // Obtener los parámetros actuales del slide
    $query = $wpdb->prepare("SELECT params FROM $table_name WHERE slider_id = %s AND slide_order = %s", $slider, $slide);
    $params = json_decode($wpdb->get_var($query), true);

    // Actualizar el estado del slide a publicado
    $params['publish']['state'] = 'published';
    $params_json = json_encode($params);

    $wpdb->update(
        $table_name,
        array('params' => $params_json),
        array('slider_id' => $slider, 'slide_order' => $slide)
    );
}

// Función para ocultar un slide
function ocultar_slide($wpdb, $slider, $slide) {
    $table_name = $wpdb->prefix . 'revslider_slides';
    // Obtener los parámetros actuales del slide
    $query = $wpdb->prepare("SELECT params FROM $table_name WHERE slider_id = %s AND slide_order = %s", $slider, $slide);
    $params = json_decode($wpdb->get_var($query), true);

    // Actualizar el estado del slide a no publicado
    $params['publish']['state'] = 'unpublished';
    $params_json = json_encode($params);

    $wpdb->update(
        $table_name,
        array('params' => $params_json),
        array('slider_id' => $slider, 'slide_order' => $slide)
    );
}
