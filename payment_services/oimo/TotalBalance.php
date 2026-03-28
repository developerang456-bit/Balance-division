<?php
// chdir('../../');
// require_once 'user_privileges/user_privileges_1.php';
// require_once 'includes/main/WebUI.php';
// require_once 'include/utils/utils.php';
// require_once 'vtlib/Vtiger/Module.php';


// $current_user = Users::getActiveAdminUser();
// $user = Users_Record_Model::getCurrentUserModel();



// class TotalBalance {
// 	public function createTotalBalance($data) {

// 		try {

// 			global $adb;
// 			$payments = Vtiger_Record_Model::getCleanInstance("TotalPayment");
// 			$payments->set('totalpayment', 'Безналичный расчет');
// 			$payments->set('assigned_user_id', '12');
// 			foreach ($data as $key => $value) {
// 				$payments->set($key, $value);
// 			}

// 			$payments->set('mode', 'create');
// 			$payments->save();
// 			$dataId = $payments->getId();

// 			// $sql = $adb->run_query_allrecords("SELECT p.cf_txnid, p.amount FROM vtiger_payments p
// 			// 								INNER JOIN vtiger_crmentity vc ON p.paymentsid = vc.crmid 
// 			// 								WHERE vc.deleted = 0
// 			// 								AND p.paymentsid = $dataId");
// 			// $result = $sql[0];
// 			// $keys_to_extract = ['cf_txnid', 'amount'];
// 			// $new_array = array_intersect_key($result, array_flip($keys_to_extract));
// 			// $new_array['command'] = "Платеж успешно сохранен в системе.";
// 			// $new_array['action'] = "pay";
// 			// return $new_array;

// 		} catch (Exception $e) {
// 			throw new Exception($e->getMessage());
// 		}
// 	}
// }

?>