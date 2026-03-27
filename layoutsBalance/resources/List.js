jQuery(function(){

    // загрузка полей
    AppConnector.request({
        module: 'Balance',
        action: 'GetFields'
    }).then(function(data){

        data.result.forEach(function(field){
            jQuery('#field').append(
                `<option value="${field}">${field}</option>`
            );
        });

    });


    // сохранение правила
    jQuery('#saveRule').click(function(){

        let field = jQuery('#field').val();
        let value = jQuery('#value').val();
        let action = jQuery('#action').val();

        if(!field || !value || !action){
            alert('Заполните все поля');
            return;
        }

        AppConnector.request({
            module: 'Balance',
            action: 'SaveRule',
            field: field,
            value: value,
            action_service: action
        }).then(function(){
            alert('Правило сохранено');
        });

    });

});