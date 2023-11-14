jQuery(document).ready(function($) {


    $('input[type="radio"]').change(function() {
        const radioBtn = $(this);
        const menuId = radioBtn.val();
        $.post(MenuVars.ajaxurl, {
            action: 'save_menu_selection',
            user_id: MenuVars.currentUserId,
            menu_id: menuId
        }, function(response) {
            if (!response.success) {
                radioBtn.prop('checked', false);
            }
        });
    });


    /** Invitados **/
    $("#invitado-popup").dialog({
        autoOpen: false,
        modal: true
    });

    $(".menu-add-invitados").click(function(e) {
        e.preventDefault();
        var menuId = $(this).data('menu-id');
        var fechaPlato = $(this).closest('.menu-day').find('.menu-date').text().replace('Día ', '');

        $("#invitado-menu-id").val(menuId);
        $("#invitado-menu-fecha").val(fechaPlato);
        $("#invitado-popup").dialog("open");
    });

    $("#invitado-form").submit(function(e) {
        e.preventDefault();
    
        var formData = $(this).serialize();
        var menuId = $("#invitado-menu-id").val();
        
        formData += '&invited_by=' + MenuVars.currentUserId;
    
        (function(menuId) {
            $.ajax({
                type: "POST",
                url: MenuVars.ajaxurl,
                data: formData + "&action=save_invitado",
                success: function(response) {
                    if (response.success) {
                        var invitadoName = $("#invitado-name").val();
                        var invitadoId = response.data.invitado_id;
    
                        var menuItem = $(".menu-item input[value='" + menuId + "']").closest('.menu-item');
                        if (menuItem.length) {
                            menuItem.find('span:last-child').prepend(invitadoName + ' <button class="delete-invitado" data-invitado-id="' + invitadoId + '"><i class="fa fa-trash" aria-hidden="true"></i></button><br>');
                        } else {
                            console.error("No se encontró el elemento del menú para el ID:", menuId);
                        }
    
                        $("#invitado-popup").dialog("close");
                        $("#invitado-name").val("");
                    } else {
                        alert("Error al guardar el invitado: " + (response.data && response.data.message ? response.data.message : "Error desconocido"));
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error("Error AJAX:", textStatus, errorThrown);
                }
            });
        })(menuId);
    });


    $(document).on('click', '.delete-invitado', function() {
        var invitadoId = $(this).data('invitado-id');
    
        $.ajax({
            type: "POST",
            url: MenuVars.ajaxurl,
            data: {
                action: 'delete_invitado',
                invitado_id: invitadoId,
                invited_by: MenuVars.currentUserId // Añade esta línea
            },
            success: function(response) {
                if (response.success) {
                    $(this).prev().remove();
                    $(this).next().remove();
                    $(this).remove();
                } else {
                    alert("Error al eliminar el invitado.");
                }
            }
        });
    });
    
    

    // Manejo del checkbox de ausencia
    
    // Al cargar la página
    $(document).ready(function() {
        // Verifica cada checkbox de ausencia
        $('.ausente-checkbox').each(function() {
            const checkbox = $(this);
            const dayContainer = checkbox.closest('.menu-day');
            
            // Si el checkbox está marcado (ausente)
            if (checkbox.is(':checked')) {
                // Deshabilita los radio buttons y botones de invitar platos para ese día
                dayContainer.find('input[type="radio"]').prop('disabled', true);
                dayContainer.find('.menu-add-invitados').prop('disabled', true);
            }
        });
    });

    
    // Al marcar el checkbox de ausente
    $('.ausente-checkbox').change(function() {
        const checkbox = $(this);
        const fecha = checkbox.data('fecha');
        const ausente = checkbox.is(':checked');
        const dayContainer = checkbox.closest('.menu-day');
    
        // Obtener la hora actual del servidor desde la variable que pasaste a JavaScript
        const currentTime = new Date(ServerTimeVars.current_time);
    
        // Al marcar como ausente, se deshabilitan las selecciones y los botones de invitación
        
         if (ausente) {
            dayContainer.find('.menu-add-invitados').prop('disabled', true);
        } else {
           // Al desmarcar ausente, comprobar si la fecha actual es menor a la fecha del botón de radio o si aún no ha pasado la hora límite
            dayContainer.find('input[type="radio"]').each(function() {
                const buttonDate = new Date($(this).data('fecha') + 'T00:00:00'); // Asumiendo que $(this).data('fecha') tiene el formato 'YYYY-MM-DD'
                const isDaytime = $(this).hasClass('dia'); // O utiliza el método que tengas para determinar si es de día o de noche
                const disableDaytime = isDaytime && currentTime.getHours() >= 10;
                const isNighttime = $(this).hasClass('noche');
                const disableNighttime = isNighttime && currentTime.getHours() >= 19;
            
                // Habilitar el botón si la fecha actual es menor o si aún no ha pasado la hora límite
                if (currentTime < buttonDate || (!disableDaytime && !disableNighttime)) {
                    $(this).prop('disabled', false);
                }
            });
            
            // Restaurar el estado de los botones de invitar de forma similar
            dayContainer.find('.menu-add-invitados').each(function() {
                // Solo habilitar los botones de invitar si su correspondiente radio button está habilitado
                const radioEnabled = $(this).closest('.menu-item').find('input[type="radio"]').is(':enabled');
                $(this).prop('disabled', !radioEnabled);
            });

        }
        // Restaurar el estado de los botones de invitar de forma similar
 
        if (ausente) {
            dayContainer.find('input[type="radio"]').prop('disabled', true);
            dayContainer.find('.menu-add-invitados').prop('disabled', true);
        } else {
            // Al desmarcar ausente, comprobar si la fecha actual es menor a la fecha del botón de radio o si aún no ha pasado la hora límite
            dayContainer.find('input[type="radio"]').each(function() {
                const buttonDate = new Date($(this).data('fecha') + 'T00:00:00'); // Asumiendo que $(this).data('fecha') tiene el formato 'YYYY-MM-DD'
                const isDaytime = $(this).hasClass('dia'); // O utiliza el método que tengas para determinar si es de día o de noche
                const disableDaytime = isDaytime && currentTime.getHours() >= 10;
                const isNighttime = $(this).hasClass('noche');
                const disableNighttime = isNighttime && currentTime.getHours() >= 19;
    
                // Habilitar el botón si la fecha actual es menor o si aún no ha pasado la hora límite
                if (currentTime < buttonDate || (!disableDaytime && !disableNighttime)) {
                    $(this).prop('disabled', false);
                }
            });
            // Restaurar el estado de los botones de invitar de forma similar
            dayContainer.find('.menu-add-invitados').prop('disabled', function() {
                return $(this).prevAll('input[type="radio"]').first().prop('disabled');
            });
        }
    
        // Envío de la información de ausencia a la base de datos a través de AJAX
        $.post(MenuVars.ajaxurl, {
            action: 'save_ausencia',
            fecha: fecha,
            ausente: ausente ? 1 : 0,
            //motivo: motivo // Se envía el valor predeterminado o string vacío
        }, function(response) {
            if (!response.success) {
                alert(response.data.message);
                checkbox.prop('checked', !ausente);
                dayContainer.find('input[type="radio"]').prop('disabled', !ausente);
                dayContainer.find('.menu-add-invitados').prop('disabled', !ausente);
            }
        });
    });

    
    $('.nota-checkbox').change(function() {
        const checkbox = $(this);
        const fecha = checkbox.data('fecha');
        const nota = checkbox.is(':checked');
        let mensaje = '';
        const dayContainer = checkbox.closest('.menu-day');
        if (nota) {
            mensaje = prompt("Agrega una nota");
            if (!mensaje) {
                checkbox.prop('checked', false);
                return;
            }
        } 
    
        $.post(MenuVars.ajaxurl, {
            action: 'save_nota',
            fecha: fecha,
            nota: mensaje
        }, function(response) {
            if (!response.success) {
                //alert(response.data.message);
                checkbox.prop('checked', !nota);
                
            }
        });
    });

});


