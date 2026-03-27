<?php
class Balance_DeleteRule_Action extends Vtiger_Action_Controller {
    public function process(Vtiger_Request $request){
        global $adb;
        $id = $request->get('id');
        $adb->pquery("DELETE FROM vtiger_balance_rules WHERE id=?", [$id]);
        $response = new Vtiger_Response();
        $response->setResult(['success'=>true]);
        $response->emit();
    }
}