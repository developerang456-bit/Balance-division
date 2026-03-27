<?php

class Balance_GetAllOptions_Action extends Vtiger_Action_Controller {

    public function process(Vtiger_Request $request) {

        global $adb;

        $result = [];

        // 1) Поля
        $res = $adb->pquery("
            SELECT vf.fieldlabel    
            FROM vtiger_blocks f
            INNER JOIN vtiger_field vf ON vf.block = f.blockid
            WHERE f.blocklabel = 'Balance information'
        ", []);
        $fields = [];
        while($row = $adb->fetchByAssoc($res)){
            $fields[] = $row['fieldlabel'];
        }
        $result['fields'] = $fields;

        // 2) Значения (ServiceCategory)
        // Передаём и ID, и название, чтобы на фронте
        // показывать только название, но сохранять именно ID
        $res = $adb->pquery("
            SELECT vr.servicecategory, vr.servicecategoryid 
            FROM vtiger_servicecategory vr
        ", []);
        $values = [];
        while($row = $adb->fetchByAssoc($res)){
            $values[] = [
                'id'    => $row['servicecategoryid'],
                'label' => $row['servicecategory'],
            ];
        }
        $result['values'] = $values;

        // 3) Действия (cf_paid_service)
        // Аналогично передаём ID и название: показываем название, сохраняем ID.
        $res = $adb->pquery("
            SELECT rc.cf_paid_service, rc.cf_paid_serviceid 
            FROM vtiger_cf_paid_service rc
            WHERE rc.cf_paid_service != 'Общая услуга'
        ", []);
        $actions = [];
        while($row = $adb->fetchByAssoc($res)){
            $actions[] = [
                'id'    => $row['cf_paid_serviceid'],
                'label' => $row['cf_paid_service'],
            ];
        }
        $result['actions'] = $actions;

        $response = new Vtiger_Response();
        $response->setResult($result);
        $response->emit();
    }
}