<?php
class Balance_UpdateRule_Action extends Vtiger_Action_Controller {

    public function process(Vtiger_Request $request) {
        global $adb;

        $id = $request->get('id');
        $field_label = $request->get('field');      // выбранное пользователем
        $service_category = $request->get('value');
        $action_service = $request->get('action_service');

        // Берём fieldname из базы по field_label
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

        // Обновляем запись
        $adb->pquery(
            "UPDATE vtiger_balance_rules 
             SET field_label=?, fieldname=?, service_category=?, action_service=? 
             WHERE id=?",
            [$field_label, $fieldname, $service_category, $action_service, $id]
        );

        $response = new Vtiger_Response();
        $response->setResult(['success'=>true]);
        $response->emit();
    }
}