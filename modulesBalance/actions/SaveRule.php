<?php
class Balance_SaveRule_Action extends Vtiger_Action_Controller {

    public function process(Vtiger_Request $request) {
        global $adb;

        $field_label = $request->get('field');      // выбранное пользователем
        // В value теперь приходит ID категории услуги (servicecategoryid)
        $service_category = $request->get('value');
        $action_service = $request->get('action_service');

        // Берём fieldname из базы по выбранному field_label
        $res = $adb->pquery("
            SELECT vf.fieldname
            FROM vtiger_blocks f
            INNER JOIN vtiger_field vf ON vf.block = f.blockid
            WHERE f.blocklabel = 'Balance information' AND vf.fieldlabel = ?
        ", [$field_label]);

        $fieldname = '';
        if($adb->num_rows($res) > 0){
            $fieldname = $adb->query_result($res, 0, 'fieldname');
        }

        // Сохраняем всё в vtiger_balance_rules
        $adb->pquery(
            "INSERT INTO vtiger_balance_rules (field_label, fieldname, service_category, action_service) VALUES (?, ?, ?, ?)",
            [$field_label, $fieldname, $service_category, $action_service]
        );

        $response = new Vtiger_Response();
        $response->setResult(['success'=>true]);
        $response->emit();
    }
}