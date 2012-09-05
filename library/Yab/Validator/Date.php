<?php
/**
 * Yab Framework
 *
 * @category   Yab_Validator
 * @package    Yab_Validator_Date
 * @author     Yann BELLUZZI
 * @copyright  (c) 2010 YBellu
 * @license    http://www.ybellu.com/yab-framework/license.html
 * @link       http://www.ybellu.com/yab-framework 
 */

class Yab_Validator_Date extends Yab_Validator_Abstract {

	const NOT_VALID = 'Value is not a valid date "$1"';

	public function _validate($value) {

		$format = $this->get('format');

		$filter = new Yab_Filter_Date();

		$filter->set('format', $format);

		$value = $filter->filter($value);

		$format = preg_quote($format, '#');

		$format = strtr($format, array(

		// RACCOURCI

		// Identique � "%m/%d/%y" 	Exemple : 02/05/09 pour le 5 F�vrier 2009
		'%D' => '%m/%d/%y', 	

		// Identique � "%Y-%m-%d" (utilis� habituellement par les bases de donn�es) 	Exemple : 2009-02-05 pour le 5 f�vrier 2009
		'%F' => '%Y-%m-%d', 

		// Identique � "%I:%M:%S %p" 	Exemple : 09:34:17 PM pour 21:34:17
		'%r' => '%I:%M:%S', 	

		// Identique � "%H:%M" 	Exemple : 00:35 pour 12:35 AM, 16:44 pour 4:44 PM
		'%R' => '%H:%M', 	

		// Identique � "%H:%M:%S" 	Exemple : 21:34:17 pour 09:34:17 PM
		'%T' => '%H:%M:%S', 	

		// OPTIONS

		// Nom abr�g� du jour de la semaine  	De Sun � Sat
		'%a' => '[a-zA-Z]{3}', 

		// Nom complet du jour de la semaine 	De Sunday � Saturday
		'%A' => '[a-zA-Z]+', 

		// Jour du mois en num�rique, sur 2 chiffres (avec le z�ro initial) 	De 01 � 31
		'%d' => '(01|02|03|04|05|06|07|08|09|'.implode('|', range(10, 31)).')', 

		// Jour du mois, avec un espace pr�c�dant le premier chiffre 	De 1 � 31
		'%e' => '('.implode('|', range(1, 31)).')', 	

		// Jour de l'ann�e, sur 3 chiffres avec un z�ro initial 	001 � 366
		// '%j' => '('.implode('|', range(1, 31)).')', 	

		// Repr�sentation ISO-8601 du jour de la semaine 	De 1 (pour Lundi) � 7 (pour Dimanche)
		'%u' => '('.implode('|', range(1, 7)).')',

		// Repr�sentation num�rique du jour de la semaine 	De 0 (pour Dimanche) � 6 (pour Samedi)
		'%w' => '('.implode('|', range(0, 6)).')',

		// Num�ro de la semaine de l'ann�e donn�e, en commen�ant par le premier Lundi comme premi�re semaine 	13 (pour la 13�me semaine pleine de l'ann�e)
		// '%U' => '',

		// Num�ro de la semaine de l'ann�e, suivant la norme ISO-8601:1988, en commen�ant comme premi�re semaine, la semaine de l'ann�e contenant au moins 4 jours, et o� Lundi est le d�but de la semaine 	De 01 � 53 (o� 53 compte comme semaine de chevauchement)
		// '%V' => '', 	

		// Une repr�sentation num�rique de la semaine de l'ann�e, en commen�ant par le premier Lundi de la premi�re semaine 	46 (pour la 46�me semaine de la semaine commen�ant par un Lundi)
		// '%W' => '', 	

		// Nom du mois, abr�g�, suivant la locale 	De Jan � Dec
		'%b' => '[a-zA-Z]{3}', 	

		// Nom complet du mois, suivant la locale 	De January � December
		'%B' => '[a-zA-Z]+', 	

		// Nom du mois abr�g�, suivant la locale (alias de %b) 	De Jan � Dec
		'%h' => '[a-zA-Z]{3}', 	

		// Mois, sur 2 chiffres 	De 01 (pour Janvier) � 12 (pour D�cembre)
		'%m' => '(01|02|03|04|05|06|07|08|09|'.implode('|', range(10, 12)).')', 	

		// Repr�sentation, sur 2 chiffres, du si�cle (ann�e divis�e par 100, r�duit � un entier) 	19 pour le 20�me si�cle
		// '%C' => '', 	

		// Repr�sentation, sur 2 chiffres, de l'ann�e, compatible avec les standards ISO-8601:1988 (voyez %V) 	Exemple : 09 pour la semaine du 6 janvier 2009
		'%g' => '[0-9]{2}', 	

		// La version compl�te � quatre chiffres de %g 	Exemple : 2008 pour la semaine du 3 janvier 2009
		// '%G' => '', 	

		// L'ann�e, sur 2 chiffres 	Exemple : 09 pour 2009, 79 pour 1979
		'%y' => '[0-9]{2}', 	

		// L'ann�e, sur 4 chiffres 	Exemple : 2038
		'%Y' => '[0-9]{4}', 	

		// L'heure, sur 2 chiffres, au format 24 heures 	De 00 � 23
		'%H' => '(00|01|02|03|04|05|06|07|08|09|'.implode('|', range(10, 23)).')',

		// Heure, sur 2 chiffres, au format 12 heures 	De 01 � 12
		'%I' => '(01|02|03|04|05|06|07|08|09|'.implode('|', range(10, 12)).')',

		// ('L' minuscule) 	Heure, au format 12 heures, avec un espace pr�c�dant de compl�tion pour les heures sur un chiffre 	De 1 � 12
		'%l' => '('.implode('|', range(1, 12)).')',

		// Minute, sur 2 chiffres 	De 00 � 59
		'%M' => '(00|01|02|03|04|05|06|07|08|09|'.implode('|', range(10, 59)).')',

		// 'AM' ou 'PM', en majuscule, bas� sur l'heure fournie 	Exemple : AM pour 00:31, PM pour 22:23
		'%p' => '(AM|PM)', 	

		// 'am' ou 'pm', en minuscule, bas� sur l'heure fournie 	Exemple : am pour 00:31, pm pour 22:23
		'%P' => '(am|pm)', 	

		// Seconde, sur 2 chiffres 	De 00 � 59
		'%S' => '(00|01|02|03|04|05|06|07|08|09|'.implode('|', range(10, 59)).')',

		// Repr�sentation de l'heure, bas�e sur la locale, sans la date 	Exemple : 03:59:16 ou 15:59:16
		// '%X' => '', 	

		// Soit le d�calage horaire depuis UTC, ou son abr�viation (suivant le syst�me d'exploitation) 	Exemple : -0500 ou EST pour l'heure de l'Est
		// '%z' => '', 	

		// Le d�calage horaire ou son abr�viation NON fournie par %z (suivant le syst�me d'exploitation) 	Exemple : -0500 ou EST pour l'heure de l'Est
		// '%Z' => '', 	

		// Date et heure pr�f�r�es, bas�es sur la locale 	Exemple : Tue Feb 5 00:45:10 2009 pour le 4 F�vrier 2009 � 12:45:10 AM
		// '%c' => '', 		

		// Timestamp de l'�poque Unix (identique � la fonction time()) 	Exemple : 305815200 pour le 10 Septembre 1979 08:40:00 AM
		'%s' => '[0-9]+', 	

		// Repr�sentation pr�f�r�e de la date, bas�e sur la locale, sans l'heure 	Exemple : 02/05/09 pour le 5 F�vrier 2009
		// '%x ' => '',	

		// Une nouvelle ligne ("\n")
		'%n' => '\n
		',

		// Une tabulation ("\t")
		'%t' => '\t',

		// Le caract�re de pourcentage ("%")
		'%%' => '%', 	

		));

		if(!preg_match('#'.$format.'#i', $value))
			$this->addError('NOT_VALID', self::NOT_VALID, $format);

	}

}

// Do not clause PHP tags unless it is really necessary