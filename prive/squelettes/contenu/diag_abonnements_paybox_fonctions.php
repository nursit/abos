<?php

function where_abonnements_paybox_ok() {

	$where = "SELECT MAX(LLLL.id_objet) as id_transaction,LLLL.id_abonnement from spip_abonnements_liens AS LLLL WHERE LLLL.objet='transaction' GROUP BY LLLL.id_abonnement";
	$where = "SELECT A.id_abonnement FROM spip_transactions AS T INNER JOIN ($where) AS A ON T.id_transaction=A.id_transaction WHERE T.reglee='oui'";
	$where = "id_abonnement IN ($where)";
	return $where;
}
