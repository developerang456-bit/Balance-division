<?php

function UpdateDebt($ws_entity) {
    global $adb;

    $ws_id = $ws_entity->getId();
    $module = $ws_entity->getModuleName();
    if (empty($ws_id) || empty($module)) {
        return;
    }

    $crmid = vtws_getCRMEntityId($ws_id);
    if ($crmid <= 0) {
        return;
    }

    if ($module === 'Payments') {
        if ($ws_entity->get("cf_paid_object")) {
            $estates_id = explode("x", $ws_entity->get("cf_paid_object"))[1];
        } else {
            return;
        }
    } elseif ($module === 'Invoice') {
        if ($ws_entity->get("cf_estate_id")) {
            $estates_id = explode("x", $ws_entity->get("cf_estate_id"))[1];
        } else {
            return;
        }
    } elseif ($module === 'Estates') { 
        $estates_id = $crmid;
    } else {
        return;
    }

    // Получаем правила из базы
    $rulesQuery = $adb->pquery("SELECT
                vbr.fieldname,
                sc.servicecategory AS service_category,
                pc.cf_paid_service as action_service
            FROM vtiger_balance_rules vbr
            LEFT JOIN vtiger_servicecategory sc
                ON sc.servicecategoryid = vbr.service_category
                LEFT JOIN vtiger_cf_paid_service pc ON pc.cf_paid_serviceid = vbr.action_service
            ORDER BY vbr.createdtime DESC", []);
    $categories = [];
    if ($adb->num_rows($rulesQuery) > 0) {
        for ($i = 0; $i < $adb->num_rows($rulesQuery); $i++) {
            $fieldname = $adb->query_result($rulesQuery, $i, 'fieldname');
            $service_category = $adb->query_result($rulesQuery, $i, 'service_category');
            $action_service = $adb->query_result($rulesQuery, $i, 'action_service');

            $categories[$service_category] = [
                'paid_service' => $action_service,
                'field' => $fieldname
            ];
        }
    }

    foreach ($categories as $category => $config) {
        $paid_service = $config['paid_service'];
        $balance_field = $config['field'];

        // Считаем сумму по счетам
        $invsumQuery = $adb->pquery("
            SELECT 
                SUM(vi.margin) AS invoice_sum,
                COUNT(*) AS invoice_count
            FROM 
                vtiger_invoice inv 
                INNER JOIN vtiger_crmentity vc ON vc.crmid = inv.invoiceid 
                INNER JOIN vtiger_inventoryproductrel vi ON vi.id = inv.invoiceid 
                INNER JOIN vtiger_service vs ON vs.serviceid = vi.productid
            WHERE 
                vc.deleted = 0 
                AND vs.servicecategory = ?
                AND inv.cf_estate_id = ?", 
            [$category, $estates_id]);

        $invoice_sum = (float)($adb->num_rows($invsumQuery) > 0 ? $adb->query_result($invsumQuery, 0, 'invoice_sum') : 0);
        $invoice_count = (int)($adb->num_rows($invsumQuery) > 0 ? $adb->query_result($invsumQuery, 0, 'invoice_count') : 0);

        // Считаем сумму по пеням
        $PenaltysumQuery = $adb->pquery("
            SELECT SUM(vp.cf_penalty_amount) as penalty_amount 
            FROM vtiger_penalty vp 
            INNER JOIN vtiger_invoice vi ON vi.invoiceid = vp.cf_invoiceid 
            INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.penaltyid 
            INNER JOIN vtiger_crmentity vc2 ON vc2.crmid = vi.invoiceid 
            WHERE vc.deleted = 0 
            AND vc2.deleted = 0 
            AND vp.cf_service = ?
            AND vi.cf_estate_id = ?", 
            [$category, $estates_id]);

        $Penalty_sum = (float)($adb->num_rows($PenaltysumQuery) > 0 ? $adb->query_result($PenaltysumQuery, 0, 'penalty_amount') : 0);
        $invoice_sum += $Penalty_sum;

        // Считаем сумму по платежам
        $paysumQuery = $adb->pquery("
            SELECT 
                SUM(vp.amount) AS pay_amount,
                COUNT(*) AS pay_count 
            FROM vtiger_payments vp
            INNER JOIN vtiger_crmentity vc ON vc.crmid = vp.paymentsid  
            WHERE 
                vc.deleted = 0 
                AND vp.cf_paid_service = ?
                AND vp.cf_pay_type = 'Приход'
                AND vp.cf_status != 'Отменен'
                AND vp.cf_paid_object = ?",
            [$paid_service, $estates_id]);

        $amount = (float)($adb->num_rows($paysumQuery) > 0 ? $adb->query_result($paysumQuery, 0, 'pay_amount') : 0);
        $pay_count = (int)($adb->num_rows($paysumQuery) > 0 ? $adb->query_result($paysumQuery, 0, 'pay_count') : 0);

        $balance = round($invoice_sum - $amount, 2);

        // Обновляем запись
        $updateQuery = "
            UPDATE vtiger_estates 
            SET 
                {$balance_field} = ?,
                cf_payment_qnt = ?,
                cf_invoice_qnt = ?,
                cf_payment_amnt = ?,
                cf_invoice_amnt = ?
            WHERE estatesid = ?";
        $adb->pquery($updateQuery, [$balance, $pay_count, $invoice_count, $amount, $invoice_sum, $estates_id]);
    }
}


function generateEstateLs($ws_entity)
{
    global $adb;
    $estate_id = explode('x', $ws_entity->getId())[1];
    $cf_object_type = $ws_entity->data['cf_object_type'];

    if ($cf_object_type == 'Физ. лицо') {

        $max_ls = $adb->run_query_field("SELECT MAX(CAST(SUBSTRING_INDEX(es.estate_number, '-', 1) AS UNSIGNED)) 
                                        FROM vtiger_estates es
                                        INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
                                        WHERE vc.deleted = 0
                                        AND es.cf_object_type = 'Физ. лицо'");

        $max_ls_int = intval($max_ls);
        $max_ls_int++;


        $unique_suffix = sprintf('%06d', $max_ls_int);

        $max_ls_str = $unique_suffix;

        $adb->pquery("INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES ('$estate_id', 'Estates', '$max_ls_str', 'Services')", array());
        // $max_ls_int . '-0';// Добавление профикса
    } elseif ($cf_object_type == 'Юр. лицо') {
        $max_ls = $adb->run_query_field("SELECT MAX(es.estate_number)
                                        FROM vtiger_estates es
                                        INNER JOIN vtiger_crmentity vc ON es.estatesid = vc.crmid 
                                        WHERE vc.deleted = 0
                                        AND es.cf_object_type = 'Юр. лицо'");

        $max_ls_int = intval($max_ls);
        $max_ls_int++;

        $unique_suffix = sprintf('%06d', $max_ls_int);

        $max_ls_str = $unique_suffix;

        $adb->pquery("INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES ('$estate_id', 'Estates', '$max_ls_str', 'Services')", array());

        // list(, $number) = explode('-', $max_ls);
        // $max_ls_int = intval($number);
        // $max_ls_int++;
        // $max_ls_str = '00-' . $max_ls_int;// Добавление профикса
    }

    $adb->pquery("UPDATE vtiger_estates es
                  SET es.estate_number = '$max_ls_str'
                  WHERE es.estatesid = ?", array($estate_id));
}



// function addService($ws_entity) {


//     global $adb;
//     $estate_id = explode('x', $ws_entity->getId())[1];
//     $cf_object_type = $ws_entity->data['cf_object_type'];


//     if ($cf_object_type == 'Физ. лицо') {
//         $adb->pquery("DELETE FROM vtiger_crmentityrel WHERE  `crmid`= $estate_id AND `module`='Estates' AND `relmodule`='Services'", array());

//         $adb->pquery("INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES ('$estate_id', 'Estates', '58269', 'Services')", array());

//     } elseif ($cf_object_type == 'Юр. лицо') {
//         $adb->pquery("DELETE FROM vtiger_crmentityrel WHERE  `crmid`= $estate_id AND `module`='Estates' AND `relmodule`='Services'", array());

//         $adb->pquery("INSERT INTO vtiger_crmentityrel (crmid, module, relcrmid, relmodule) VALUES ('$estate_id', 'Estates', '61044', 'Services')", array());

//     }
// }

?>