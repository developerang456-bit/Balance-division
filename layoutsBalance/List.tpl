<style>
html, body {
    height: 100%;
    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
    background-color: #f4f5f7;
    color: #333;
}

.full-height {
    min-height: calc(100vh - 60px); /* высота без верхнего меню vtiger */
    padding: 20px;
}

h2 {
    color: #5e5e5e; /* vtiger зеленый */
    margin-bottom: 20px;
    font-weight: 600;
}

#addRule {
    margin-bottom: 20px;
}

#newRule {
    margin-top: 20px;
    padding: 20px;
    border: 1px solid #d0d0d0;
    border-radius: 6px;
    background-color: #ffffff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    display: none; /* по умолчанию скрыто */
}

#newRule h4 {
    margin-bottom: 15px;
    color: #3a8e3a;
}

#newRule select {
    margin-bottom: 15px;
}




.table {
    background-color: #fff;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05);
}

.table th {
    background-color: #e6f2e6;
    color: #3a8e3a;
    font-weight: 600;
}



.table td, .table th {
    vertical-align: middle !important;
}

.btn-sm {
    padding: 3px 8px;
    font-size: 13px;
}
</style>

<div class="container-fluid full-height">

    <h2>Balance Settings</h2>

    <button class="btn btn-primary" id="addRule">Добавить правило</button>

    <br><br>

    <table class="table table-bordered" id="rulesTable">
        <thead>
            <tr>
                <th>Баланс</th>
                <th>Категория услуги</th>
                <th>Оплачиваемая услуга</th>
                <th>Действие</th>
            </tr>
        </thead>
        <tbody>
            <!-- правила будут загружаться через JS -->
        </tbody>
    </table>

    <!-- Форма добавления/редактирования правила -->
    <div id="newRule">
        <h4>Новое правило</h4>

        <select id="field" class="form-control">
            <option value="">Выберите поле баланса</option>
        </select>

        <select id="value" class="form-control">
            <option value="">Выберите категорию услуги</option>
        </select>

       <select id="action_service" class="form-control" name="action_service">
    <option value="">Выберите оплачеваемую услугу</option>
</select>

        <button id="saveRule" class="btn btn-success">Сохранить правило</button>
    </div>

</div>

<script>
jQuery(document).ready(function(){

    // Показ формы добавления/редактирования
    jQuery('#addRule').click(function(){
        jQuery('#newRule').slideToggle();
        jQuery('#saveRule').removeData('edit-id'); // очистка режима редактирования
    });

    // Загрузка всех вариантов полей/значений/действий
    AppConnector.request({
        module: 'Balance',
        action: 'GetAllOptions'
    }).then(function(data){
        var fSelect = jQuery('#field');
        var vSelect = jQuery('#value');
        var aSelect = jQuery('#action_service');

        jQuery.each(data.result.fields, function(i,item){
            fSelect.append('<option value="'+item+'">'+item+'</option>');
        });

       
        jQuery.each(data.result.values, function(i,item){
            vSelect.append('<option value="'+item.id+'">'+item.label+'</option>');
        });

        jQuery.each(data.result.actions, function(i,item){
            aSelect.append('<option value="'+item.id+'">'+item.label+'</option>');
        });
    });

    // Загрузка сохранённых правил
    loadSavedRules();

    // Сохранение/обновление правила
    jQuery('#saveRule').click(function(){
        var field = jQuery('#field').val();
        var value = jQuery('#value').val();
        var action = jQuery('#action_service').val(); // правильно берём селект
        var editId = jQuery(this).data('edit-id'); // id для редактирования

        if(!field || !value || !action){
            alert('Заполните все поля');
            return;
        }

        if(editId){
            // обновление существующего правила
            AppConnector.request({
                module:'Balance',
                action:'UpdateRule',
                id: editId,
                field: field,
                value: value,
                action_service: action
            }).then(function(){
                alert('Правило обновлено');
                jQuery('#newRule').slideUp();
                jQuery('#saveRule').removeData('edit-id');
                loadSavedRules();
            });
        } else {
            // создание нового правила
            AppConnector.request({
                module:'Balance',
                action:'SaveRule',
                field: field,
                value: value,
                action_service: action
            }).then(function(response){
                alert('Правило успешно сохранено');
                jQuery('#newRule').slideUp();
                jQuery('#field').val('');
                jQuery('#value').val('');
                jQuery('#action_service').val('');
                loadSavedRules();
            });
        }
    });

    // Кнопка редактирования
    jQuery(document).on('click','.editRule',function(){
        var tr = jQuery(this).closest('tr');
        var id = tr.data('id');
        var field = tr.find('td:eq(0)').text();
        // Для категории услуги используем ID, сохранённый в data-servicecategoryid,
        // но в таблице продолжаем показывать человекочитаемое название.
        var value = tr.data('servicecategoryid');
        var action = tr.find('td:eq(2)').text();

        jQuery('#newRule').slideDown();
        jQuery('#field').val(field);
        jQuery('#value').val(value);
        jQuery('#action_service').val(action);
        jQuery('#saveRule').data('edit-id', id); // сохраняем id редактируемого правила
    });

    // Кнопка удаления
  jQuery(document).ready(function(){
    jQuery(document).on('click','.deleteRule', function(){
        if(!confirm('Удалить это правило?')) return;

        var tr = jQuery(this).closest('tr');
        var id = tr.data('id');

        AppConnector.request({
            module: 'Balance',
            action: 'DeleteRule',
            id: id
        }).then(function(response){
            if(response.success){
                tr.remove();
                // уведомление через Vtiger_Helper_Js
                Vtiger_Helper_Js.showPnotify({
                    text: 'Правило удалено',
                    type: 'success'
                });
            } else {
                Vtiger_Helper_Js.showPnotify({
                    text: 'Ошибка удаления',
                    type: 'error'
                });
            }
        });
    });
});
});

// Функция загрузки правил
function loadSavedRules(){
    AppConnector.request({
        module:'Balance',
        action:'GetSavedRules'
    }).then(function(data){
        var tbody = jQuery('#rulesTable tbody');
        tbody.empty();

        jQuery.each(data.result, function(i,row){
            tbody.append(
                '<tr data-id="'+row.id+'" data-servicecategoryid="'+(row.service_category_id || '')+'">'+
                '<td>'+row.field_label+'</td>'+
                '<td>'+row.service_category+'</td>'+
                '<td>'+row.action_service+'</td>'+
                '<td>'+
                '<button class="btn btn-sm btn-primary editRule">Редактировать</button> '+
                '<button type="button" class="btn btn-sm btn-danger deleteRule">Удалить</button>'+
                '</td>'+
                '</tr>'
            );
        });
    });
}
</script>