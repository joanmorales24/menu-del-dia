<?php
/*
Plugin Name: Menú del Día
Description: Plugin para cargar un menú desde un archivo CSV.
Version: 1.0
Author: Joan Morales
AuthorURI: https://joanmorales.com
*/

function formatearFecha($fecha) {
    // Convierte la fecha a formato "dd/mm/yyyy"
    return date('d/m/Y', strtotime($fecha));
}

function crear_tabla_menu() {
    global $wpdb;

    $tabla = $wpdb->prefix . 'menu_del_dia';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        fecha date NOT NULL,
        menu text NOT NULL,
        tipo varchar(10) NOT NULL,
        opcion char(1) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'crear_tabla_menu');

/***menu***/
function menu_admin_page() {
    add_menu_page('Menú del día', 'Menú del Día', 'manage_options', 'menu-del-dia', 'menu_page_html');
    
    // Agregar submenús
    add_submenu_page('menu-del-dia', 'Subir Menú', 'Subir Menú', 'manage_options', 'menu-del-dia', 'menu_page_html');
    
    add_submenu_page('menu-del-dia', 'Platos por opción', 'Platos por opción', 'manage_options', 'reporte-menu-del-dia', 'mostrar_reporte_menu_del_dia');
    add_submenu_page('menu-del-dia', 'Platos por persona', 'Platos por persona', 'manage_options', 'informe-personalizado', 'mostrar_informe_personalizado');
}

add_action('admin_menu', 'menu_admin_page');



function menu_page_html() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_del_dia';
   
    if (isset($_POST['action']) && $_POST['action'] == 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $wpdb->update($tabla, array('menu' => $_POST['menu']), array('id' => $id));
            echo '<div class="updated"><p>Plato actualizado con éxito.</p></div>';
        }
    }

   if (isset($_FILES['menu_csv']) && current_user_can('manage_options')) {
    if ($_FILES['menu_csv']['error'] == UPLOAD_ERR_OK && $_FILES['menu_csv']['type'] == 'text/csv') {
        $archivo = fopen($_FILES['menu_csv']['tmp_name'], 'r');
        while (($linea = fgetcsv($archivo)) !== FALSE) {
            $fechaArray = explode('/', $linea[0]);
            $fechaFormatoSQL = $fechaArray[2] . '-' . $fechaArray[1] . '-' . $fechaArray[0];

            // Consulta para verificar si ya existe un registro con la misma fecha, tipo y opción
            $exists = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $tabla WHERE fecha = %s AND tipo = %s AND opcion = %s",
                    $fechaFormatoSQL, $linea[2], $linea[3]
                )
            );

            // Si no existe, entonces lo insertamos
            if (!$exists) {
                $wpdb->insert($tabla, array(
                    'fecha' => $fechaFormatoSQL,
                    'menu' => $linea[1],
                    'tipo' => $linea[2],
                    'opcion' => $linea[3]
                ));
            }
        }
        fclose($archivo);
        echo '<div class="updated"><p>Menú cargado con éxito.</p></div>';
    } else {
        echo '<div class="error"><p>Error al subir el archivo. Asegúrate de que es un archivo CSV.</p></div>';
    }
}


    ?>

      <div class="wrap">
        
        <br><br>
        <h2>Subir Menú</h2>
        <p>El archivo a subír debe estar en CSV separado por comas.</p>
        <p>Descarga el archivo de ejemplo: <a href="/wp-content/uploads/2023/11/ejemplo-Menu-Refrigerio.csv" target="_blank">Descargar Archivo</a></p>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="menu_csv" accept=".csv">
            <input type="submit" value="Subir Menú" class="button button-primary">
        </form>
        <div id="notification" style="margin: 20px 0;"></div>


        <h2>Menú Cargado</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Menú</th>
                    <th>Tipo</th>
                    <th>Opción</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $items = $wpdb->get_results("SELECT * FROM $tabla ORDER BY fecha ASC, tipo ASC, opcion ASC", ARRAY_A);

                foreach ($items as $item) {
                    $fechaArray = explode('-', $item['fecha']);
                    $fechaFormatoNormal = $fechaArray[2] . '/' . $fechaArray[1] . '/' . $fechaArray[0];
                    echo '<tr>';
                    echo '<td>' . esc_html($fechaFormatoNormal) . '</td>';
                    echo '<td>
                        <input type="text" data-id="'. esc_attr($item['id']) .'" class="menu-input" value="'. esc_attr($item['menu']) .'" readonly>
                      <span class="check-icon" style="display: none; color: green;">✓</span>
                    </td>';
                    echo '<td>' . esc_html($item['tipo']) . '</td>';
                    echo '<td>' . esc_html($item['opcion']) . '</td>';
                    echo '<td><button class="edit-button" data-text="Editar">Editar</button></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputs = document.querySelectorAll('.menu-input');
        const buttons = document.querySelectorAll('.edit-button');
        const notification = document.getElementById('notification');
        let isSaving = false;
        let loadingInterval;
    
        function showError(message) {
            notification.textContent = message;
            notification.style.color = 'red';
            setTimeout(() => {
                notification.textContent = '';
            }, 5000); // Ocultar el mensaje de error después de 5 segundos.
        }
        
        buttons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
        
                const input = e.target.closest('tr').querySelector('.menu-input');
                input.removeAttribute('readonly');
                input.focus();
        
                const originalValue = input.value;
                const checkIcon = e.target.closest('tr').querySelector('.check-icon');
        
                e.target.textContent = "Guardar";
                e.target.classList.add('button-primary');
    
                input.addEventListener('blur', async function() {
                    if (input.value.trim() === '') {
                        input.value = originalValue;
                        showError("El campo no puede quedar vacío.");
                        return;
                    }
    
                    if (originalValue !== input.value && !isSaving) {
                        isSaving = true;
    
                        e.target.textContent = "Guardando";
                        let dots = 0;
                        loadingInterval = setInterval(() => {
                            e.target.textContent = `Guardando${'.'.repeat(dots)}`;
                            dots = (dots + 1) % 4;
                        }, 500);
        
                        const formData = new FormData();
                        formData.append('action', 'update_menu_item');
                        formData.append('id', input.dataset.id);
                        formData.append('menu', input.value);
        
                        const response = await fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        });
        
                        const responseData = await response.json();
    
                        clearInterval(loadingInterval);
        
                        if (responseData.success) {
                            checkIcon.style.display = 'inline';
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            input.value = originalValue;
                            checkIcon.style.display = 'none';
                            e.target.textContent = "Editar";
                            e.target.classList.remove('button-primary');
                        }
        
                        isSaving = false;
                    } else {
                        input.setAttribute('readonly', true);
                    }
                });
        
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        input.blur();
                    }
                });
            });
        });
    });


    </script>
    <?php
}

function update_menu_item() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_del_dia';

    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $menu = sanitize_text_field($_POST['menu']);

    if ($id > 0) {
        $result = $wpdb->update($tabla, array('menu' => $menu), array('id' => $id));

        if ($result !== false) {
            wp_send_json_success();
        } else {
            // Aquí estamos agregando más información sobre el error
            $last_error = $wpdb->last_error;
            wp_send_json_error(array('message' => 'Error al actualizar. Detalle: ' . $last_error));
        }
    } else {
        wp_send_json_error(array('message' => 'ID inválido'));
    }
}
add_action('wp_ajax_update_menu_item', 'update_menu_item');
add_action('wp_ajax_nopriv_update_menu_item', 'update_menu_item');



/*****INTERFAZ DEL USUARIO******/
function crear_tabla_seleccion_menu() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_seleccion_menu';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id mediumint(9) NOT NULL,
        menu_id mediumint(9) DEFAULT NULL,
        fecha date NOT NULL,
        tipo varchar(10) DEFAULT NULL,
        ausente BOOLEAN DEFAULT 0,
        motivo TEXT,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'crear_tabla_seleccion_menu');



function menu_selector_shortcode() {
    global $wpdb;

    $current_user_id = get_current_user_id();
    if (!$current_user_id) {
        return "Debes iniciar sesión para acceder a esta página.";
    }
    
    $tabla_menu = $wpdb->prefix . 'menu_del_dia';
    $tabla_seleccion = $wpdb->prefix . 'menu_seleccion_menu';
    
    $current_date_string = date('Y-m-d');
    $menu_items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM $tabla_menu WHERE fecha >= %s ORDER BY fecha ASC, tipo ASC, opcion ASC", 
            $current_date_string
        ), 
        ARRAY_A
    );

    $previous_date = "";
    $output .= '<form method="POST" action="#">'; 
    
    $current_date = new DateTime("now", new DateTimeZone('America/Argentina/Cordoba'));
    $current_date_string = $current_date->format('Y-m-d');
    $current_time_string = $current_date->format('H:i:s');
    
    $tipo_previo = '';
    foreach ($menu_items as $item) {
        $selected_menu = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $tabla_seleccion WHERE user_id = %d AND fecha = %s AND tipo = %s",
                $current_user_id,
                $item['fecha'],
                $item['tipo']
            ),
            ARRAY_A
        );

        if ($item['tipo'] == "noche" && $tipo_previo != "noche") {
            // Asegúrate de que no sea el primer elemento del arreglo
            if ($tipo_previo != '') {
                $output .= '<div style="margin:10px;"><hr style="border-top-color: #ddd"></div>'; // Insertar divisor
            }
        }
            
        if ($previous_date != $item['fecha'] ) {
            if ($previous_date != "") {
                $output .= '</div>'; // Cierra el contenedor del día anterior
            }
                
                $output .= '<div class="menu-day">'; // Nuevo contenedor para el día
                $output .= '<div class="menu-header">';
                $output .= '<div class="menu-date-display">Día ' . esc_html(formatearfecha($item['fecha'])) . '</div>';
                $output .= '<div class="menu-date" style="display:none">Día ' . esc_html($item['fecha']) . '</div>';
               
                // Agregar el checkbox de ausencia aquí
                $ausenciaMarcada = $wpdb->get_var($wpdb->prepare("SELECT ausente FROM $tabla_seleccion WHERE user_id = %d AND fecha = %s", $current_user_id, $item['fecha']));
                $checked = $ausenciaMarcada ? 'checked' : '';
                $output .= '<div class="menu-ausencia">Ausente: <input type="checkbox" class="ausente-checkbox" data-fecha="' . esc_attr($item['fecha']) . '" ' . $checked . '></div>';
                
                $notaMarcada = $wpdb->get_var($wpdb->prepare("SELECT nota FROM $tabla_seleccion WHERE user_id = %d AND fecha = %s", $current_user_id, $item['fecha']));
                $checked = $notaMarcada ? 'checked' : '';
                $output .= '<div class="menu-ausencia">Nota: <input type="checkbox" class="nota-checkbox" data-fecha="' . esc_attr($item['fecha']) . '" ' . $checked . '></div>';
                $output .= '</div>';

        }
        

        
    // Inicio del elemento del menú
        
        $output .= '<div class="menu-item ' . esc_attr($item['tipo']) . '">'; // Añadir clase para tipo de menú

        // Nombre del menú
        $output .= '<span class="menu-name">' . esc_html($item['menu']) . '</span>';
        // Opción del menú
        $output .= '<span class="menu-option">Opción ' . esc_html($item['opcion']) . ' (' . esc_html($item['tipo']) . ')</span>';
    
        // Definir el nombre del grupo de radio buttons incluyendo el tipo de menú (día/noche)
          $radio_name = 'menu_selection_' . esc_attr($item['fecha']) . '_' . esc_attr($item['tipo']);
        
        
        if ($item['fecha'] == $current_date_string) {
            // Si es el día actual, aplicar lógica para deshabilitar los botones en función de la hora
            $is_daytime = $item['tipo'] == 'dia';
            $is_nighttime = $item['tipo'] == 'noche';
            // Después de determinar $is_daytime y $is_nighttime

            // Ajustar las horas de corte '10:00' y '19:00'
            $disable_daytime = $is_daytime && $current_time_string >= '00:10:00';
            $disable_nighttime = $is_nighttime && $current_time_string >= '19:00:00';
            $disabled_attr = ($disable_daytime || $disable_nighttime) ? 'disabled' : '';
            
        } else {
            // Si no es el día actual, no deshabilitar los botones
            $disabled_attr = '';
        }
        
        // Definir un atributo data-enabled para almacenar si el botón debería estar habilitado o no basado en la hora
        
        
        $data_enabled_attr = $disabled_attr ? 'false' : 'true';
        
     
        // Agregar radio button con el atributo data-enabled
        $output .= '<span class="menu-selection"><input type="radio" class="menu-option-radio ' . ($is_daytime ? 'dia' : '') . ($is_nighttime ? 'noche' : '') . '" name="' . $radio_name . '" value="' . esc_attr($item['id']) . '"' . ($selected_menu && $selected_menu['menu_id'] == $item['id'] ? ' checked' : '') . ' ' . $disabled_attr . ' data-enabled="' . $data_enabled_attr . '"></span>';

        
        $invitados = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, invitado_name FROM " . $wpdb->prefix . "menu_invitados WHERE menu_id = %d AND invited_by = %d", 
                $item['id'], 
                $current_user_id
            ), 
            ARRAY_A
        );
       
        $output .= '<span class="nombre-invitado">';
        
        foreach ($invitados as $invitado) {
            $output .= esc_html($invitado['invitado_name']);
            $output .= '<button class="delete-invitado" data-invitado-id="' . $invitado['id'] . '"><i class="fa fa-trash" aria-hidden="true"></i></button><br>';
        }
        // Agregar botón para invitar con posible atributo disabled
        // Dentro del foreach de los elementos del menú

    // Agregar un atributo data-fecha al botón de invitados para poder usarlo en el script JS
    $output .= '<button type="button" class="menu-add-invitados" data-menu-id="' . esc_attr($item['id']) . '" data-fecha="' . esc_attr($item['fecha']) . '" ' . $disabled_attr . '>Invitar plato</button>';


        
        //$output .= '<button type="button" class="menu-add-invitados" data-menu-id="' . esc_attr($item['id']) . '">Invitar este plato</button>'; // Botón habilitado
        
        
        $output .= '</span>';
        
        $output .= '</div>'; // Fin de menu-item
        $tipo_previo = $item['tipo'];
        $previous_date = $item['fecha'];
    }
    

    if ($previous_date != "") {
        $output .= '</div>'; // Cierra el último contenedor de día
    }

   
    $output .= '</form>';
    
    /**popup de invitados**/
    // En la función menu_selector_shortcode(), justo antes de 'return $output;'
    
    $output .= '
    <div id="invitado-popup" title="Agregar Invitado">
       <form id="invitado-form">
            <label for="invitado-name">Nombre del Invitado:</label>
            <input type="text" placeholder="ejemplo: José Belotti" id="invitado-name" name="invitado_name" required>
            <input type="hidden" id="invitado-menu-id" name="menu_id">
            <input type="hidden" id="invitado-menu-fecha" name="menu_fecha">

            <input type="submit" value="Agregar">
        </form>
    </div>';
    $current_time_buenos_aires = date("Y-m-d H:i:s");
    wp_localize_script('menu-selector-script', 'ServerTimeVars', array(
        'current_time' => $current_time_buenos_aires
    ));

    
    return $output;
}
add_shortcode('menu_selector', 'menu_selector_shortcode');


function enqueue_menu_selector_assets() {
    if (is_singular() && has_shortcode(get_post()->post_content, 'menu_selector')) {
        // Puedes encolar estilos y scripts específicos para el selector de menú aquí
        wp_enqueue_script('menu-selector-script', plugin_dir_url(__FILE__) . 'menu-selector.js', array('jquery'), '1.0.0', true);

        // Pasamos algunas variables al JavaScript
        wp_localize_script('menu-selector-script', 'MenuVars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'currentUserId' => get_current_user_id()
        ));
        
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
    }
}
add_action('wp_enqueue_scripts', 'enqueue_menu_selector_assets');


function save_menu_selection() {
    global $wpdb;
    date_default_timezone_set('America/Argentina/Buenos_Aires');

    $tabla = $wpdb->prefix . 'menu_seleccion_menu';
    $tabla_menu = $wpdb->prefix . 'menu_del_dia';
    
    $user_id = intval($_POST['user_id']);
    $menu_id = intval($_POST['menu_id']);
    $selected_menu = $wpdb->get_row($wpdb->prepare("SELECT fecha, tipo FROM $tabla_menu WHERE id = %d", $menu_id), ARRAY_A);

    if ($selected_menu) {
        $selected_date = $selected_menu['fecha'];
        $selected_type = $selected_menu['tipo'];

        // Comprobar si ya existe una selección para ese usuario, fecha y tipo
        $existing_selection = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $tabla WHERE user_id = %d AND fecha = %s AND tipo = %s",
            $user_id,
            $selected_date,
            $selected_type
        ));

        // Si existe, eliminar la selección anterior
        if ($existing_selection) {
            $wpdb->delete($tabla, array('id' => $existing_selection));
        }

        // Insertar la nueva selección
        $result = $wpdb->insert(
            $tabla,
            array(
                'user_id' => $user_id,
                'menu_id' => $menu_id,
                'fecha' => $selected_date,
                'tipo' => $selected_type
            )
        );
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error(['message' => 'Error al guardar la selección.']);
        }
    } else {
        wp_send_json_error(['message' => 'Error al obtener los detalles del menú.']);
    }
}
add_action('wp_ajax_save_menu_selection', 'save_menu_selection'); 
add_action('wp_ajax_nopriv_save_menu_selection', 'save_menu_selection');

/****INVITADOS****/

/**se crea la tabla menu_invitados**/
function crear_tabla_menu_invitados() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_invitados';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    menu_id mediumint(9) NOT NULL,
    invitado_name varchar(255) NOT NULL,
    fecha date NOT NULL,
    invited_by bigint(20) UNSIGNED DEFAULT NULL,
    PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'crear_tabla_menu_invitados');


/**guardar invitados**/
function save_invitado() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_invitados';
    
    $menu_id = intval($_POST['menu_id']);
    $invitado_name = sanitize_text_field($_POST['invitado_name']);
    $fecha = sanitize_text_field($_POST['menu_fecha']); // Asegúrate de enviar esta fecha desde el frontend
    
    // Obtener el ID del usuario actual
    $current_user_id = get_current_user_id();
    if($current_user_id == 0) {
        // No hay un usuario conectado
        wp_send_json_error(['message' => 'Debe estar conectado para realizar esta acción.']);
        return;
    }

    $result = $wpdb->insert($tabla, array('menu_id' => $menu_id, 'invitado_name' => $invitado_name, 'fecha' => $fecha, 'invited_by' => $current_user_id));

    if ($result) {
        $invitado_id = $wpdb->insert_id; // Obtiene el ID del último registro insertado
        wp_send_json_success(array('invitado_id' => $invitado_id));
    } else {
        wp_send_json_error(['message' => 'Error al guardar el invitado.']);
    }

}

add_action('wp_ajax_save_invitado', 'save_invitado'); 
add_action('wp_ajax_nopriv_save_invitado', 'save_invitado');

/**borrar invitados**/

function delete_invitado() {
    global $wpdb;

    $invitado_id = isset($_POST['invitado_id']) ? intval($_POST['invitado_id']) : 0;
    $current_user_id = get_current_user_id();

    if ($invitado_id) {
        $tabla = $wpdb->prefix . 'menu_invitados';

        // Verificar si el usuario actual es el que invitó
        $invited_by = $wpdb->get_var($wpdb->prepare("SELECT invited_by FROM $tabla WHERE id = %d", $invitado_id));
        if($invited_by != $current_user_id) {
            wp_send_json_error(array('message' => 'No tiene permiso para eliminar este invitado.'));
            return;
        }

        $deleted = $wpdb->delete($tabla, array('id' => $invitado_id));

        if ($deleted) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'No se pudo eliminar el invitado.'));
        }
    } else {
        wp_send_json_error(array('message' => 'ID de invitado no válido.'));
    }
}
add_action('wp_ajax_delete_invitado', 'delete_invitado'); // Si el usuario está conectado
add_action('wp_ajax_nopriv_delete_invitado', 'delete_invitado'); // Si el usuario no está conectado



/***Ausencias***/

// Modificar la tabla 'menu_seleccion_menu'
function modificar_tabla_menu_seleccion_menu() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_seleccion_menu';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "ALTER TABLE $tabla 
            ADD ausente BOOLEAN DEFAULT 0,
            ADD motivo TEXT;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'modificar_tabla_menu_seleccion_menu');

function save_ausencia() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_seleccion_menu';
    
    $user_id = get_current_user_id();
    $fecha = sanitize_text_field($_POST['fecha']);
    $ausente = isset($_POST['ausente']) && $_POST['ausente'] == 1 ? 1 : 0;
    $motivo = sanitize_text_field($_POST['motivo']);

    // Verificar si ya existe una entrada para esa fecha
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla WHERE user_id = %d AND fecha = %s", $user_id, $fecha));

    if ($exists) {
        if ($ausente) {
            // Si existe y se marca como ausente, actualizamos la entrada
            $result = $wpdb->update($tabla, array('ausente' => 1, 'motivo' => $motivo), array('user_id' => $user_id, 'fecha' => $fecha));
        } else {
            // Si existe y se desmarca como ausente, reseteamos los valores de ausente y motivo
            $result = $wpdb->update($tabla, array('ausente' => 0, 'motivo' => ''), array('user_id' => $user_id, 'fecha' => $fecha));
        }
    } else {
        if ($ausente) {
            // Si no existe y se marca como ausente, insertamos una nueva entrada
            $result = $wpdb->insert($tabla, array('user_id' => $user_id, 'fecha' => $fecha, 'ausente' => 1, 'motivo' => $motivo));
        } else {
            // Si no existe y se desmarca como ausente, no hay necesidad de hacer nada
            wp_send_json_success();
            return;
        }
    }

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => 'Error al marcar el ausente.']);
    }
}
add_action('wp_ajax_save_ausencia', 'save_ausencia');

function save_nota() {
    global $wpdb;
    $tabla = $wpdb->prefix . 'menu_seleccion_menu';
    
    $user_id = get_current_user_id();
    $fecha = sanitize_text_field($_POST['fecha']);
    $nota = sanitize_text_field($_POST['nota']); // Se cambió la variable de $motivo a $nota

    // Verificar si ya existe una entrada para esa fecha
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tabla WHERE user_id = %d AND fecha = %s", $user_id, $fecha));

    if ($exists) {
        // Si existe una entrada, actualizamos la nota
        $result = $wpdb->update($tabla, ['nota' => $nota], ['user_id' => $user_id, 'fecha' => $fecha]);
    } else {
        // Si no existe una entrada, insertamos una nueva con la nota
        $result = $wpdb->insert($tabla, ['user_id' => $user_id, 'fecha' => $fecha, 'nota' => $nota]);
    }

    if ($result !== false) { // Asegúrate de que $result no sea falso, porque 0 también es un resultado válido si no se cambian las filas
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => 'Error al guardar la nota.']);
    }
}
add_action('wp_ajax_save_nota', 'save_nota');


/***REPORTES***/
// Muestra el formulario para seleccionar las fechas
function mostrar_formulario_fecha() {
    echo '<form method="post">';
    echo '<label for="fecha_inicial">Fecha Inicial:</label>';
    echo '<input type="date" id="fecha_inicial" name="fecha_inicial" required>';
    
    echo '<label for="fecha_final">Fecha Final:</label>';
    echo '<input type="date" id="fecha_final" name="fecha_final" required>';
 
    echo '<input type="submit" value="Mostrar Informe">';
    echo '</form>';
 }
 
 function mostrar_reporte_menu_del_dia() {
    global $wpdb;
 
    echo '<div class="wrap">';
    echo '<h2>Total de platos por opción</h2><br><br>';
 
    $fecha_inicio = isset($_POST['fecha_inicio']) ? sanitize_text_field($_POST['fecha_inicio']) : '';
    $fecha_fin = isset($_POST['fecha_fin']) ? sanitize_text_field($_POST['fecha_fin']) : '';
 
    // Formulario de selección de fechas
    echo '<form method="post">';
    echo '<label for="fecha_inicio">Fecha de Inicio:</label>';
    echo '<input type="date" name="fecha_inicio" value="' . esc_attr($fecha_inicio) . '">';
    echo '<label for="fecha_fin">Fecha de Fin:</label>';
    echo '<input type="date" name="fecha_fin" value="' . esc_attr($fecha_fin) . '">';
    echo '<input type="submit" value="Filtrar">';
    echo '</form>';
    
    if(!empty($_POST)){
    // Consulta SQL para obtener el informe con fecha, tipo, plato y opciones
    $sql = "
    SELECT
        m.fecha,
        m.tipo,
        m.menu AS plato,
        m.opcion AS opciones,
        SUM(IFNULL(msm.cantidad, 0)) + SUM(IFNULL(mi.cantidad, 0)) AS cantidad_total
    FROM
        {$wpdb->prefix}menu_del_dia AS m
    LEFT JOIN
        (SELECT menu_id, COUNT(*) AS cantidad FROM {$wpdb->prefix}menu_seleccion_menu WHERE ausente = 0 GROUP BY menu_id) AS msm
    ON
        m.id = msm.menu_id
    LEFT JOIN
        (SELECT menu_id, COUNT(*) AS cantidad FROM {$wpdb->prefix}menu_invitados GROUP BY menu_id) AS mi
    ON
        m.id = mi.menu_id
    WHERE
        m.fecha BETWEEN %s AND %s  -- Filtros de fecha
    GROUP BY
        m.fecha, m.tipo, m.menu, m.opcion
    ORDER BY
        m.fecha, m.tipo, m.menu, m.opcion;
    ";
 
    $resultados = $wpdb->get_results($wpdb->prepare($sql, $fecha_inicio, $fecha_fin));
 
    // Agregar un botón de impresión
    
    echo '<button onclick="imprimirReporte()" style="margin:10px;">Imprimir Reporte</button>';
 
    // Mostrar los resultados en forma de tabla dentro de un div con un identificador
    echo '<div id="reporte-imprimible">';
    echo '<style type="text/css">';
    echo '.tabla-reporte{ font-size: 14px; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '</style>';
    echo '<table class="widefat">';
    echo '<tr><th>Fecha</th><th>Tipo</th><th>Plato</th><th>Opciones</th><th>Cantidad Total</th></tr>';
    foreach ($resultados as $fila) {
        echo '<tr>';
        echo '<td>' . formatearFecha($fila->fecha) . '</td>';
        echo '<td>' . $fila->tipo . '</td>';
        echo '<td>' . $fila->plato . '</td>';
        echo '<td>' . $fila->opciones . '</td>';
        echo '<td>' . $fila->cantidad_total . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
 
    // Script JavaScript para imprimir solo el contenido del div con el identificador "reporte-imprimible"
    echo '
    <script>
    function imprimirReporte() {
        var contenidoImprimible = document.getElementById("reporte-imprimible").innerHTML;
        var ventanaImpresion = window.open("", "", "width=600,height=600");
        ventanaImpresion.document.write("<html><head><title>Reporte de Menú del Día</title><style>body{ font-family:Poppins; font-size: 12px; }</style></head><body>");
        ventanaImpresion.document.write(contenidoImprimible);
        ventanaImpresion.document.write("</body></html>");
        ventanaImpresion.document.close();
        ventanaImpresion.print();
        
    }
    </script>';
    
    echo '</div>';
    }
 }
 
 //***otro reporte***/
 
 // Muestra la página de informes personalizados
 function mostrar_informe_personalizado() {
    echo '<div class="wrap">';
    echo '<h2>Informe de platos por usuarios/invitados</h2><br><br>';
    mostrar_formulario_fecha();
    
    // Comprueba si se ha enviado el formulario
    if (isset($_POST['fecha_inicial']) && isset($_POST['fecha_final'])) {
        $fecha_inicial = sanitize_text_field($_POST['fecha_inicial']);
        $fecha_final = sanitize_text_field($_POST['fecha_final']);
        mostrar_resultados_informe($fecha_inicial, $fecha_final);
    } 
    echo '</div>';
 }
 
 
 
 // Muestra los resultados del informe en función del rango de fechas seleccionado
 function mostrar_resultados_informe($fecha_inicial, $fecha_final) {
    global $wpdb;
 
    // Consulta SQL para obtener los datos dentro del rango de fechas seleccionado
        $sql = "
         SELECT 
             u.user_login AS Usuario,
             m.menu AS Plato_Usuario,
             msm.fecha AS Fecha_Seleccionada,
             msm.tipo AS Turno,
             IF(msm.ausente = 1, 'Ausente', '') AS Estado_Ausente,
             msm.nota AS Nota, /* Aquí se incluye la columna 'nota' */
             '-' AS Invitado,
             '-' AS Plato_Invitado,
             '-' AS Invitado_Por
         FROM 
             {$wpdb->prefix}menu_seleccion_menu msm
         LEFT JOIN {$wpdb->prefix}users u ON msm.user_id = u.ID
         LEFT JOIN {$wpdb->prefix}menu_del_dia m ON msm.menu_id = m.id
         WHERE 
             msm.fecha BETWEEN %s AND %s
         
         UNION ALL
         
         SELECT 
             '-' AS Usuario,
             '-' AS Plato_Usuario,
             mi.fecha AS Fecha_Seleccionada,
             m.tipo AS Turno,
             '-' AS Estado_Ausente,
             '-' AS Nota, /* Se añade un lugar para la nota, que siempre será '-' para invitados */
             mi.invitado_name AS Invitado,
             m.menu AS Plato_Invitado,
             u.user_login AS Invitado_Por
         FROM 
             {$wpdb->prefix}menu_invitados mi
         LEFT JOIN {$wpdb->prefix}menu_del_dia m ON mi.menu_id = m.id
         LEFT JOIN {$wpdb->prefix}users u ON mi.invited_by = u.ID
         WHERE 
             mi.fecha BETWEEN %s AND %s
         
         ORDER BY Fecha_Seleccionada, Usuario;
     ";
 
     // Asegúrate de que las fechas pasadas a la función prepare() están en el formato correcto
     $sql = $wpdb->prepare($sql, $fecha_inicial, $fecha_final, $fecha_inicial, $fecha_final);
 
 
    // Ejecuta la consulta SQL y muestra los resultados
    $resultados = $wpdb->get_results($sql);
       // Agregar un botón de impresión
    
 
    // Mostrar los resultados en forma de tabla dentro de un div con un identificador
    echo '<div id="reporte-imprimible">';
    echo '<style type="text/css">';
    echo '.tabla-reporte{ font-size: 14px; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }';
    echo 'tr:nth-child(even) { background-color: #f2f2f2; }';
    echo '</style>';
    // Muestra los resultados en forma de tabla u otro formato
    echo '<h3>Resultados para el rango de fechas seleccionado: ' . formatearFecha($fecha_inicial) . ' - ' . formatearFecha($fecha_final) . '</h3> ';
    echo '<button onclick="imprimirReporte()" style="margin:10px; text-align: left">Imprimir Reporte</button>';
    echo '<table class="widefat">';
    echo '<tr><th>Nombre de la Persona</th><th>Fecha Seleccionada</th><th>Turno</th><th>Plato a Servir</th><th>Estado</th><th>Nota</th><th>Invitado Por</th></tr>';
    foreach ($resultados as $fila) {
        echo '<tr>';
        echo '<td>' . ($fila->Usuario !== '-' ? $fila->Usuario : $fila->Invitado . ' (Invitado)') . '</td>';
        echo '<td>' . formatearFecha($fila->Fecha_Seleccionada) . '</td>';
        echo '<td>' . ($fila->Turno !== null ? $fila->Turno : 'N/A') . '</td>';
        echo '<td>' . ($fila->Plato_Usuario !== '-' ? $fila->Plato_Usuario : $fila->Plato_Invitado) . '</td>';
        echo '<td>' . $fila->Estado_Ausente . '</td>';
        echo '<td>' . $fila->Nota . '</td>'; // Muestra la nota en la fila correspondiente
        echo '<td>' . ($fila->Invitado_Por !== '-' ? $fila->Invitado_Por : 'N/A') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
       echo '
    <script>
    function imprimirReporte() {
        var contenidoImprimible = document.getElementById("reporte-imprimible").innerHTML;
        var ventanaImpresion = window.open("", "", "width=600,height=600");
        ventanaImpresion.document.write("<html><head><title>Reporte de Menú del Día</title><style>body{ font-family:Poppins; font-size: 12px; }</style></head><body>");
        ventanaImpresion.document.write(contenidoImprimible);
        ventanaImpresion.document.write("</body></html>");
        ventanaImpresion.document.close();
        ventanaImpresion.print();
        
    }
    </script>
    ';
 }
 
 
 