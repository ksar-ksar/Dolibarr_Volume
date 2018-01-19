<?php
/* Copyright (C) 2017 ksar <ksar.ksar@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modVolume_VolumeTriggers.class.php
 * \ingroup volume
 * \brief   Calculate the quantity according to extrafields 
 *		- qty
 *		- lg
 *		- lr
 *		- ht
 *
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

// Need to add the extrafields 
require_once (DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php');

//Need to add pricelib
require_once (DOL_DOCUMENT_ROOT.'/core/lib/price.lib.php');

/**
 *  Class of triggers for Volume module
 */
class InterfaceVolumeTriggers extends DolibarrTriggers
{
	/**
	 * @var DoliDB Database handler
	 */
	protected $db;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = "interfaceprix";
		$this->description = "Volume triggers.";
		// 'development', 'experimental', 'dolibarr' or version
		$this->version = 'development';
		$this->picto = 'volume@volume';
	}

	/**
	 * Trigger name
	 *
	 * @return string Name of trigger file
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * Trigger description
	 *
	 * @return string Description of trigger file
	 */
	public function getDesc()
	{
		return $this->description;
	}


	/**
	 * Function called when a Dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
        if (empty($conf->volume->enabled)) return 0;     // Module not active, we do nothing

		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
		
	    // Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action
		if ($action == 'LINEBILL_INSERT' || $action == 'LINEBILL_UPDATE')
		{
			dol_syslog("Trigger '".$this->name."' Facture detected", LOG_DEBUG);
			$main_object=new Facture($this->db);
			$main_object->fetch($object->fk_facture);
		}
		elseif ($action == 'LINEORDER_INSERT' || $action == 'LINEORDER_UPDATE')
		{
			dol_syslog("Trigger '".$this->name."' Commande detected", LOG_DEBUG);
			$main_object=new Commande($this->db);
			$main_object->fetch($object->fk_commande);
		}
		elseif ($action == 'LINEPROPAL_INSERT' || $action == 'LINEPROPAL_UPDATE')
		{
			dol_syslog("Trigger '".$this->name."' Propal detected", LOG_DEBUG);
			$main_object=new Propal($this->db);
			$main_object->fetch($object->fk_propal);
		}
		else
			return 0;   
		
		// les extrafields de la ligne de la pièce
		$extrafields = new ExtraFields($this->db);		
		// fetch optionals attributes and labels
		$extralabels=$extrafields->fetch_name_optionals_label($object->table_element);
		$object->fetch_optionals($object->rowid, $extralabels);
		
		// Si tous les paramétres existent alors on calcul la nouvelle quantitée.
		if (isset($object->array_options['options_qty']) && isset($object->array_options['options_lg']) && isset($object->array_options['options_lr']) && isset($object->array_options['options_ht']))
		{
			$new_qty = $object->array_options['options_qty'] * $object->array_options['options_lg'] * $object->array_options['options_lr'] / 100 * $object->array_options['options_ht'] / 100;
			$object->qty = price2num($new_qty,'MU');
		}
		
		// On met à jour le prix de la ligne 
		$tabprice = calcul_price_total($object->qty, $object->subprice, $object->remise_percent, $object->tva_tx, $object->localtax1_tx, $object->localtax2_tx, 0, 'HT', 0, $object->product_type);
		$object->total_ht = $tabprice[0];
		$object->total_tva = $tabprice[1];
		$object->total_ttc = $tabprice[2];
		$object->total_localtax1 = $tabprice[9];
		$object->total_localtax2 = $tabprice[10];
		
		// on met à jour mais on n'execute pas le trigger (sinon on boucle en MAJ)
		$result=$object->update($user, 1);
		if($result > 0)
			$main_object->update_price();
				
		return 0;
	}
}
