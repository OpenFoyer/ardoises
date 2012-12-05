<?php

class Rf_Ardoises_Controller extends Base_Controller {
	
	public $restful = true;

	public function get_index()
	{
		return View::make('rf::ardoises.home');
	}

	public function get_edit($login)
	{
		return View::make('rf::ardoises.one', array(
			'user' => Utilisateur::where('login', '=', $login)->first(),
		));
	}
	
	//
	// Modification des préférences d'un utilisateur
	//
	public function post_edit($login)
	{
		$user = Utilisateur::where('login', '=', $login)->first();
		$inputs = Input::all();
		
		$user->fill($inputs);
		$user->save();
		
		$login = $user->login;
		LogDB::add_flash('success', array(
			'description' => "Les préférences de l'utilisateur « $login » ont été modifiées.",
			'nomtable' => 'utilisateur',
			'idtable' => $user->id
		));
		
		return View::make('rf::ardoises.one', array(
			'user' => Utilisateur::where('login', '=', $login)->first(),
		));
	}
	
	//
	// Crédit d'une ardoise
	//
	public function post_credit($login)
	{
		$user = Utilisateur::where('login', '=', $login)->first();
		$ardoise = $user->ardoise;
		
		DB::transaction(function () use ($ardoise) {
			$credit = Credit::create(array(
				'ardoise_id' => $ardoise->id,
				'credit' => Input::get('credit'),
				'moyenpaiement_id' => Input::get('moyenpaiement')
			));
			$credit->save();
			$ardoise->montant = $ardoise->montant - $credit->credit;
			$ardoise->save();
		});
		
		$qte = Input::get('credit');
		LogDB::add_flash('success', array(
			'description' => "L'ardoise de « $login » a été créditée de $qte €.",
			'nomtable' => 'ardoise',
			'idtable' => $ardoise->id
		));
		
		return Redirect::to_action('rf::ardoises@edit.'.$login);
	}
	
	
	//
	// Création d'utilisateur et d'ardoise
	//
	public function get_add()
	{
		return View::make('rf::ardoises.add');	
	}
	
	public function post_add()
	{
		$rules = array(
			'mail' => 'required|email',
			'prenom' => 'required',
			'nom' => 'required',
			'mdp' => 'required',
			'login' => 'required|unique:utilisateur,login',
			'promo' => 'required',
			'departement_id' => 'required'
		);
		$validation = Validator::make(Input::all(), $rules);
		if ($validation->fails())
			return Redirect::to('rf/ardoises/add/')->with_errors($validation)->with_input();
		
		DB::transaction(function(){
			$ardoise = Ardoise::create(array('montant'=>'0'));
			$ardoise->save();
			$user_vars = Input::all();
			$user_vars['ardoise_id'] = $ardoise->id;
			$user = Utilisateur::create($user_vars);
			$user->mdp = md5(Input::get('mdp'));
			$user->save();
		
			$login = Input::get('login');
			LogDB::add_flash('success', array(
				'description' => "Le compte « $login » a été créé.",
				'nomtable' => 'utilisateur',
				'idtable' => $user->id
			));
				
			return Redirect::to('rf/ardoises/credit/' . $user->id);
		});
		
		return Redirect::to('rf/ardoises');
	}
	
	
	//
	// Transfert entre ardoises
	//
	public function get_transfert ()
	{
		return View::make('rf::ardoises.transfert');
	}
	
	public function post_transfert ()
	{
		DB::transaction(function(){
			$debiteur = Utilisateur::where_login(Input::get('debiteur'))->first();
			$debiteur_a = $debiteur->ardoise;
			$crediteur = Utilisateur::where_login(Input::get('crediteur'))->first();
			$crediteur_a = $crediteur->ardoise;
			$montant = Input::get('montant');
			
			$t = Transfert::create(array(
				'ardoise_id_debiteur' => $debiteur_a->id,
				'ardoise_id_crediteur' => $crediteur_a->id,
				'montant' => $montant
			));
			$t->save();
			
			$debiteur_a->montant = $debiteur_a->montant + $montant; // il donne l'argent /!\
			$debiteur_a->save();
			$crediteur_a->montant = $crediteur_a->montant - $montant; // il gagne l'argent
			$crediteur_a->save();
			
			LogDB::add_flash('success', array(
				'description' => "Transfert de $montant € effectué de « " . $debiteur->login . " » vers « " . $crediteur->login . " ».",
				'nomtable' => 'transfert',
				'idtable' => $t->id
			));
		});
		return View::make('rf::ardoises.transfert');
	}

}