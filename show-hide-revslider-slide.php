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
    if (isset($_POST['submit']) && isset($_POST['slide'])) {
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
    <div class="turnero-info">
        <h1>Show Hide RevSlider Slide</h1>
        <form method="post">
            <label for="slider">Selecciona el slider:</label>
            <select name="slider" id="slider">
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
    $slides = $wpdb->get_results($wpdb->prepare("SELECT slide_order, params FROM {$wpdb->prefix}revslider_slides WHERE slider_id = %s", $slider_id));

    $options_html = '';
    foreach ($slides as $slide) {
        $params = json_decode($slide->params);
        $slide_title = isset($params->title) ? $params->title : 'Slide ' . $slide->slide_order;
        $options_html .= "<option value='{$slide->slide_order}'>{$slide_title}</option>";
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
    if (strpos($params, '"state":"unpublished"') !== false) {
        return 'oculto';
    } else {
        return 'visible';
    }
}

// Función para mostrar un slide
function mostrar_slide($wpdb, $slider, $slide) {
    $table_name = $wpdb->prefix . 'revslider_slides';
    $params = '{"publish":{"state":"published"}}'; // Nuevos parámetros para mostrar el slide
    $wpdb->update(
        $table_name,
        array('params' => $params),
        array('slider_id' => $slider, 'slide_order' => $slide)
    );
}

// Función para ocultar un slide
function ocultar_slide($wpdb, $slider, $slide) {
    $table_name = $wpdb->prefix . 'revslider_slides';
    $params = '{"publish":{"state":"unpublished"}}'; // Nuevos parámetros para ocultar el slide
    $wpdb->update(
        $table_name,
        array('params' => $params),
        array('slider_id' => $slider, 'slide_order' => $slide)
    );
}
