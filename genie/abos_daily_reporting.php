<?php
/*
 * Paiement Bancaire
 * module de paiement bancaire multi prestataires
 * stockage des transactions
 *
 * Auteurs :
 * Cedric Morin, Nursit.com
 * (c) 2012-2015 - Distribue sous licence GNU/GPL
 *
 */
if (!defined('_ECRIRE_INC_VERSION')) return;

function genie_abos_daily_reporting_dist($t){

	if (defined('_ABOS_EMAIL_REPORTING')){

		// entre minuit et 7h du matin
		$now = time();
		$last = '';
		if (isset($GLOBALS['meta']['abos_daily_reporting_last'])){
			$last = $GLOBALS['meta']['abos_daily_reporting_last'];
		}
		if ($last!==date('Y-m-d', $now)){

			// commencer par mettre a jour les stats
			$compter = charger_fonction('compter', 'abos');
			$compter();

			// il faut avoir configure un ou des emails de notification
			if (defined('_ABOS_EMAIL_REPORTING')){
				include_spip('inc/filtres');

				$texte = abos_reporting_decompte();

				$texte = "<html>$texte</html>";
				$header = "MIME-Version: 1.0\n" .
					"Content-Type: text/html; charset=" . $GLOBALS['meta']['charset'] . "\n" .
					"Content-Transfer-Encoding: 8bit\n";
				$sujet = "[" . $GLOBALS['meta']['nom_site'] . "] Reporting Abonnements";

				$envoyer_mail = charger_fonction('envoyer_mail', 'inc');
				$envoyer_mail(_ABOS_EMAIL_REPORTING, $sujet, $texte, '', $header);

				spip_log("Envoi reporting quotidien", 'abos');
			}

			ecrire_meta('abos_daily_reporting_last', date('Y-m-d', $now));
		}

	}

	return 1;
}
