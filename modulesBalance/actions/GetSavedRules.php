<?php
class Balance_GetSavedRules_Action extends Vtiger_Action_Controller {

    public function process(Vtiger_Request $request) {
        global $adb;

        // Вытаскиваем ID вместе с остальными полями.
        // В service_category теперь хранится servicecategoryid,
        // поэтому джойнимся к vtiger_servicecategory, чтобы получить название.
        $result = $adb->pquery("
           SELECT
                vbr.id,
                vbr.field_label,
                vbr.service_category AS servicecategoryid,
                sc.servicecategory AS service_category,
                vbr.action_service as 'action_serviceid',
                pc.cf_paid_service as 'action_service'
            FROM vtiger_balance_rules vbr
            LEFT JOIN vtiger_servicecategory sc
                ON sc.servicecategoryid = vbr.service_category
                LEFT JOIN vtiger_cf_paid_service pc ON pc.cf_paid_serviceid = vbr.action_service
            ORDER BY vbr.createdtime DESC
        ", []);

        $rules = [];
        while($row = $adb->fetchByAssoc($result)){
            $rules[] = [
                'id' => $row['id'], // ID правила
                'field_label' => $row['field_label'],
                // Показываем на UI название, но дополнительно передаём и ID
                'service_category' => $row['service_category'],      // название
                'service_category_id' => $row['servicecategoryid'],  // ID (servicecategoryid)
                'action_service' => $row['action_service']
            ];
        }

        $response = new Vtiger_Response();
        $response->setResult($rules);
        $response->emit();
    }
}