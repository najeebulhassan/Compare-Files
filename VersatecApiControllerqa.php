<?php

namespace App\Http\Controllers;

use App\Helpers\Versatec;
use App\User;
use App\CardList;
use App\TransactionList;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use stdClass;
use App\Mail\Testing;
use PDF;
use App\Exports\AccountStatement;
use Maatwebsite\Excel\Facades\Excel;


class VersatecApiController extends Controller
{

    public function showAccountInfo()
    {

        return response(app(Versatec::class)->sendAccountInfo());
    }

    public function showAccountStatus(Request $request)
    {
        $data      =  $request->all();

        $validator =  Validator::make($data, [
            'account' => 'required',
        ]);
        $year = $request->input('year') != 0  ? $request->input('year') : 0;
        $month = $request->input('month') != 0 ? $request->input('month') : 0;
        $account = $request->input('account');
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->getAllAccountStatus($account, $year, $month));
        // $res= response(app(Versatec::class)->getPendingBalance($account));

        $res_org = json_decode($res->original);
        dd($res_org);
    }

    public function showFloatingTransactions(Request $request)
    {
        //
        $data = $request->all();

        $validator = Validator::make($data, [
            'account' => 'required',
        ]);
        $account = $request->input('account');
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->getFloatingTransactions($account));

        $json = json_decode($res->original);
        $my_data            =   new stdClass();
        $my_data->InfoTran  =   $json->InfoTran;
        $jmodel             =   $json->Model;

        $sort   =   array();
        foreach ($jmodel as $key => $row) {
            $sort[$key] = $row->FechaTrx;
        }
        array_multisort($sort, SORT_DESC, $jmodel);
        $my_data->Model =   $jmodel;
        return json_encode($my_data);
    }

    public function showCardBalance(Request $request)
    {
        //
        $data = $request->all();

        $validator = Validator::make($data, [
            'account' => 'required',
        ]);
        $account = $request->input('account');
        $year = $request->input('year') != 0  ? $request->input('year') : 0;
        $month = $request->input('month') != 0 ? $request->input('month') : 0;
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->getCardBalance($account, $year, $month));

        $res_org = json_decode($res->original);

        $total_balance = abs($res_org->SaldoFinalML);
        $available_balance = abs($res_org->DisponibleML);
        $pending_balance = ($total_balance - $available_balance);
        $array = array();
        $array['total_balance'] = number_format($total_balance, 2);
        $array['available_balance'] = number_format($available_balance, 2);
        $array['pending_balance'] = round($pending_balance, 2) > 0 ? number_format($pending_balance, 2) : "0.00";
        return $array;
    }

    public function showAccountMovements(Request $request)
    {
        //
        $data = $request->all();
        $validator = Validator::make($data, [
            'account' => 'required',
            'card' => 'required',
        ]);

        $year = $request->input('year') != 0  ? $request->input('year') : 0;
        $month = $request->input('month') != 0 ? $request->input('month') : 0;
        $account = $request->input('account');
        $card = $request->input('card');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->getAccountMovements($account, $card, $year, $month));

        $json = json_decode($res->original);
        $tran = TransactionList::all()->toArray();
        if (count($tran) > 0) {
            foreach ($tran as $key => $d) {
                $newds[] = (object) $d;
            }
            $merge = array_merge($newds, $json->Model);
        } else {
            $merge = $json->Model;
        }

        $my_data            =   new stdClass();
        $my_data->InfoTran  =   $json->InfoTran;
        $jmodel             =   $merge;

        $sort   =   array();
        foreach ($jmodel as $key => $row) {
            $sort[$key] = $row->FechaOrigen;
        }
        array_multisort($sort, SORT_DESC, $jmodel);
        $my_data->Model =   $jmodel;
        return json_encode($my_data);
    }

    public function addManagementCardRep(Request $request)
    {

        $data = $request->all();
        $validator = Validator::make($data, [
            'gestion' => 'required',
            'idCuenta' => 'required',
            'idTarjeta' => 'required',

        ]);
        $gestion = $request->input('gestion');
        $idCuenta = $request->input('idCuenta');
        $idTarjeta = $request->input('idTarjeta');

        $descripcion = $request->input('descripcion');
        $userName = $request->input('userName');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        return response(app(Versatec::class)->addCardReplacementReq($gestion, $idCuenta, $idTarjeta, $descripcion, $userName));
    }

    public function listNewCards(Request $request)
    {
        $res = response(app(Versatec::class)->showCardsList());
        print_r(json_decode($res->original));
    }

    public function inputCardManagement(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'codManagement' => 'required',
            'account' => 'required',
            'card' => 'required',
            'desc' => 'required',

        ]);
        $codManagement = $request->input('codManagement');
        $account = $request->input('account');
        $card = $request->input('card');
        $desc = $request->input('desc');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->newCardManagement($codManagement, $account, $card, $desc));
        return $res->original;
    }

    public function markCardAssociated(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'card' => 'required',
            'account' => 'required',
            'client_code' => 'required',
            'username' => 'required',
            'idPlastico' => 'required',
            'user_id' => 'required',
            'cif' => 'required',
            'expiry_date' => 'required',
            'card_status' => 'required',
            'nombre_tarjeta' => 'required',
            'tarjeta_digitos' => 'required',

        ]);
        $card = $request->input('card');
        $account = $request->input('account');
        $client_code = $request->input('client_code');
        $username = $request->input('username');

        $idPlastico = $request->input('idPlastico');
        $user_id = $request->input('user_id');
        $cif = $request->input('cif');
        $expiry_date = $request->input('expiry_date');
        $card_status = $request->input('card_status');
        $nombre_tarjeta = $request->input('nombre_tarjeta');
        $tarjeta_digitos = $request->input('tarjeta_digitos');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $associate_response = response(app(Versatec::class)->associateAccountToCard($card, $account, $client_code, $username));

        if ($associate_response->original == 'Ok') {
            $card = CardList::updateOrCreate(
                ['idPlastico' => $idPlastico],
                [
                    'Cuenta' => $account, 'user_id' => $user_id, 'Tarjeta' => $card, 'IdPlastico' => $idPlastico, 'codigoCliente' => $client_code, 'Cif' => $cif, 'card_status' => $card_status,
                    'expiry_date' => $expiry_date, 'is_associated' => 1, 'nombre_tarjeta' => $nombre_tarjeta, 'tarjeta_digitos' => $tarjeta_digitos
                ]
            );
            if ($card == true) {
                $subject = "Associated Plastico";
                $message = "Your Plastico Has Been Associated Successfully";
                $email = User::where('id', $user_id)->where('cod_cliente', $client_code)->select('email')->first();
                $data = new stdClass();
                $data->message = $message;
                $data->subject = $subject;
                Mail::to($email->email)->send(new Testing($data));
                $associate_message = "Your Plastico Has Been Associated Successfully";
            } else {
                $associate_message = "Some Error Occurred";
            }
        } else {
            $associate_message = "Something Went Wrong";
        }
        return $associate_message;
    }

    public function activateNewCard(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'account' => 'required',
            'card' => 'required',
            'client_code' => 'required',
            'username' => 'required',
            'idPlastico' => 'required',

        ]);
        $account = $request->input('account');
        $card = $request->input('card');
        $client_code = $request->input('client_code');
        $username = $request->input('username');
        $idPlastico = $request->input('idPlastico');


        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $activate_response = response(app(Versatec::class)->makeCardActive($account, $card, $client_code, $username));

        if ($activate_response->original == 'Ok') {
            CardList::where('IdPlastico', $idPlastico)->update(
                ['is_activated' => 1, 'card_status' => 'PLASTICO OK']
            );

            $subject = "Activated Plastico";
            $message = "Plastico Has Been Activated Successfully";
            $email = User::where('cod_cliente', $client_code)->select('email')->first();
            $data = new stdClass();
            $data->message = $message;
            $data->subject = $subject;
            Mail::to($email->email)->send(new Testing($data));

            $activate_message = "Plastico Has Been Activated Successfully";
        } else {
            $activate_message = "Something Went Wrong";
        }
        return $activate_message;
    }

    public function requestCardActivation(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'idPlastico' => 'required',
        ]);
        $idPlastico = $request->input('idPlastico');


        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }

        $cardlist = CardList::where('IdPlastico', $idPlastico)->update(
            ['is_activated' => 2]
        );

        if ($cardlist == true) {

            $subject = "Request Plastico For Activation";
            $message = "Your Plastico Has Been Requested For Activation Successfully";
            $user_id = Cardlist::where('IdPlastico', $idPlastico)->select('user_id')->first();
            $email = User::where('id', $user_id->user_id)->select('email')->first();
            $data = new stdClass();
            $data->message = $message;
            $data->subject = $subject;
            Mail::to($email->email)->send(new Testing($data));
            $activate_message = "Your Plastico Has Been Requested For Activation Successfully";
        } else {
            $activate_message = "Something Went Wrong";
        }

        return $activate_message;
    }

    public function viewCardInfo(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'card' => 'required',
        ]);
        $card = $request->input('card');

        if ($validator->fails()) {

            return response(['errors' => $validator->errors()]);
        }
        return response(app(Versatec::class)->getCardInfo($card));
    }

    public function listUserCards(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'code' => 'required',
        ]);
        $code = $request->input('code');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->showUserCards());

        $new_array = array();
        $desc = "desc";
        $flag = 0;
        foreach ($res->original as $key => $value) {

            if ($value->CodigoCliente != $code) {
                $res->original[$key]->name = "hello";
                unset($res->original[$key]);
            }
        };

        return array_values($res->original);
    }

    public function addDebCredProc(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'account_id' => 'required',
            'card_id' => 'required',
            'movement_type' => 'required',
            'concept' => 'required',
            'transaction_amouont' => 'required',
            'recipt' => 'required',
            'description' => 'required',
            'username' => 'required',
        ]);
        $account_id = $request->input('account_id');
        $card_id = $request->input('card_id');
        $movement_type = $request->input('movement_type');
        $concept = $request->input('concept');

        $transaction_amouont = $request->input('transaction_amouont');
        $recipt = $request->input('recipt');
        $description = $request->input('description');
        $username = $request->input('username');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        return response(app(Versatec::class)->addDebCredManagement($account_id, $card_id, $movement_type, $concept, $transaction_amouont, $recipt, $description, $username));
    }

    public function dummyCardManagement(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'codManagement' => 'required',
            'account' => 'required',
            'card' => 'required',
            'desc' => 'required',

        ]);
        $codManagement = $request->input('codManagement');
        $account = $request->input('account');
        $card = $request->input('card');
        $desc = $request->input('desc');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }

        $res = response(app(Versatec::class)->newCardManagement($codManagement, $account, $card, $desc));

        if ($codManagement == 56) {

            if ($res->original == "Ok") {
                CardList::where('Tarjeta', $card)->where('Cuenta', $account)->update(
                    ['card_status' => 'CUENTA BLOQUEADA', 'is_activated' => 3]
                );
                $cardlist = "Your Card Has Been Blocked Successfully";
            } else {

                $cardlist = $res->original . 'block';
            }
        } elseif ($codManagement == 34) {
            if ($res->original == "Ok") {
                CardList::where('Tarjeta', $card)->where('Cuenta', $account)->update(
                    ['card_status' => 'PLASTICO OK', 'is_activated' => 1]
                );
                $cardlist = "Your Card Has Been Activated Successfully";
            } else {
                $cardlist = $res->original . 'unblock';
            }
        } else {
            // do nothing
        }

        return $cardlist;
    }

    public function activateDummyCard(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'account' => 'required',
            'card' => 'required',
            'client' => 'required',
            'username' => 'required',

        ]);
        $account = $request->input('account');
        $card = $request->input('card');
        $client = $request->input('client');
        $username = $request->input('username');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $asres = response(app(Versatec::class)->associateAccountToCard($card, $account, $client, $username));
        if ($asres->original == "Ok") {
            $actres = response(app(Versatec::class)->makeCardActive($account, $card, $client, $username));
            if ($actres->original == "Ok") {
                $cardlist = "Your Card Has Been Activated";
            } else {
                $cardlist = "Your Card Has Already Been Activated. Or " . $actres->original;
            }
        } else {
            $cardlist = "Your Card Has Already Been Associated. Or " . $asres->original;
        }

        return $cardlist;
    }

    public function addNewCard(Request $request)
    {
        $input = $request->all();
        $validator = Validator::make($input, [
            'cod_cliente' => 'required',
            'user_id' => 'required',
        ]);
        $user_id = $request->input('user_id');
        $client_code = $request->input('cod_cliente');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }

        $input['num_identificacion'] = $input['cod_cliente'];
        $input['primer_nombre'] = $input['username'];
        $input['primer_apellido'] = $input['username'];
        $input['telefono'] = $input['celular'];
        $input['ciclo_facturacion'] = 1;
        $input['tipo_identificacion'] = 1;
        $input['telefono_empresa'] = $input['celular'];
        $input['role_id'] = 2;
        $input['tipo_tarjeta'] = '009';
        $input['sucursal'] = '01';
        $input['cupo'] = 0;
        $input['promotor'] = '4000000000';
        $input['profesion'] = '999';
        $input['empresa'] = 'TDSNA';
        $input['lugar_entrega'] = '01';
        $input['nacionalidad'] = 'PA';

        $input_obj = (object) $input;
        $user = User::where('id', $user_id)->where('cod_cliente', $client_code)->first();
        if ($user->card_request == 0 &&  $user->card_request != 1) {
            $res = response(app(Versatec::class)->sendAccountInfo($input_obj));
            $json_res = json_decode($res->original);
            $mes = $json_res->InfoTran->ReturnMessage;
           
            if ($mes == "Ok") {
                $subject = "Plastico Neuvo";
                $message = "Plastico Neuvo Has Been Added";
                $data = new stdClass();
                $data->message = $message;
                $data->subject = $subject;
                Mail::to($user->email)->send(new Testing($data));
                $resp = $user->update(['card_request' => 1]);
                if ($resp == true) {
                    $cardlist = $message;
                }
            } else {
                $cardlist = "Plastico Neuvo Could Not Be Added";
            }
        } else {
            $cardlist = "You card has already been requested";
        }
        return $cardlist;
    }



    public function listDummyCards()
    {
        $res = response(app(Versatec::class)->showCardsList());

        $resp = json_decode($res->original);

        foreach ($resp as $key => $value) {

            $card = $resp[$key]->Tarjeta;
            $account = $resp[$key]->Cuenta;

            $expiry_date = response(app(Versatec::class)->getCardInfo($card));
            if ($expiry_date->original != null) {
                $respo = json_decode($expiry_date->original);
                $resp[$key]->expiry_date = $respo->Vencimiento;
                $resp[$key]->card_status = $respo->Estado;
            } else {
                $resp[$key]->expiry_date = "";
            }

            // $year = 0;
            // $month = 0;

            // $userstatus = response(app(Versatec::class)->getAccountStatus($account, $year, $month));
            // if ($userstatus->original != null) {
            //     $res_org = json_decode($userstatus->original);
            //     $resp[$key]->nombre_tarjeta = $res_org->Nombre;
            //     $resp[$key]->tarjeta_digitos = $res_org->Tarjeta;
            // } else {
            //     $resp[$key]->nombre_tarjeta = null;
            //     $resp[$key]->tarjeta_digitos = null;
            // }
        }
        return $resp;
    }

    public function listDummyUserCards(Request $request)
    {
        ini_set('max_execution_time', 180);
        $data = $request->all();
        $validator = Validator::make($data, [
            'code' => 'required',

        ]);
        $code = $request->input('code');
        $user_id = $request->input('user_id');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $year = 0;
        $month = 0;

        $usercard = response(app(Versatec::class)->showCardsByCode($code));
        foreach ($usercard->original as $key => $value) {

            $card = $value->Tarjeta;
            $account = $value->Cuenta;

            $expiry_date = response(app(Versatec::class)->getCardInfo($card));

            if ($expiry_date->original != null) {
                $respo = json_decode($expiry_date->original);
                $usercard->original[$key]->expiry_date = $respo->Vencimiento;
                $usercard->original[$key]->card_status = $respo->Estado;
            } else {
                $usercard->original[$key]->expiry_date = "";
            }

            $userstatus = response(app(Versatec::class)->getAccountStatus($account, $year, $month));
            if ($userstatus->original != null) {
                $res_org = json_decode($userstatus->original);

                $usercard->original[$key]->nombre_tarjeta = $res_org->Nombre;
                $usercard->original[$key]->tarjeta_digitos = $res_org->Tarjeta;
            } else {
                $usercard->original[$key]->nombre_tarjeta = null;
                $usercard->original[$key]->tarjeta_digitos = null;
            }
        }
        return array_values($usercard->original);
    }

    public function showCardsConglomerado(Request $request)
    {

        $account = $request->input('account') ?: '';
        $response = response(app(Versatec::class)->getCardsConglomerado($account));
        return $response;
    }

    public function listAssociatedCards()
    {

        $cardlist = CardList::where('is_associated', 1)->where('is_activated', 0)->get()->toJson(JSON_PRETTY_PRINT);
        return $cardlist;
    }

    public function listActivatedCards()
    {

        $cardlist = CardList::where('user_id', '!=', 199)->where('is_associated', 1)->whereIn('is_activated', [1, 2, 3])->get()->toJson(JSON_PRETTY_PRINT);
        return $cardlist;
    }

    public function downloadCardData()
    {

        $cardlist = CardList::where('is_associated', 1)->where('is_activated', 1)->orWhere('is_activated', 2)->select('nombre_tarjeta', 'tarjeta_digitos')->get();
        $main_array =   "[";
        $counts         =   $cardlist->count();
        foreach ($cardlist as $key => $transaction) {
            $main_array .= "[$transaction->nombre_tarjeta,$transaction->tarjeta_digitos]";
            if ($key != ($counts - 1)) {
                $main_array .= ",";
            }
        }
        $main_array .= "]";

        return $main_array;
    }

    public function listUserAssociatedCards(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'client_code' => 'required',

        ]);
        $client_code = $request->input('client_code');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $cardlist = CardList::where('codigoCliente', $client_code)->where('is_associated', 1)->where('is_activated', 0)->get()->toJson(JSON_PRETTY_PRINT);
        return $cardlist;
    }

    public function listUserActivatedCards(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'client_code' => 'required',

        ]);
        $client_code = $request->input('client_code');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $cardlist = CardList::where('codigoCliente', $client_code)->whereIn('is_activated', [1, 2, 3])->get()->toJson(JSON_PRETTY_PRINT);
        return $cardlist;
    }

    public function showCardDigits(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'client_code' => 'required',

        ]);
        $client_code = $request->input('client_code');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $year = 0;
        $month = 0;
        $cardlist = CardList::where('codigoCliente', $client_code)->whereIn('is_activated', [1, 2, 3])->select('tarjeta_digitos', 'nombre_tarjeta')->first();
        if ($cardlist == true) {
            $digits = substr($cardlist->tarjeta_digitos, -4);
            $cardname = $cardlist->nombre_tarjeta;
        } else {
            $usercard = response(app(Versatec::class)->showCardsByCode($client_code));
            foreach ($usercard->original as $key => $value) {
                $account = $value->Cuenta;
                $userstatus = response(app(Versatec::class)->getAccountStatus($account, $year, $month));
                if ($userstatus->original != null) {
                    $res_org = json_decode($userstatus->original);
                    $digits = substr($res_org->Tarjeta, -4);
                    $cardname = $res_org->Nombre;
                }
            }
        }
        $carddata = array(['digits' => $digits, 'cardname' => $cardname]);
        return $carddata;
    }

    public function showCuentaTarjeta(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'client_code' => 'required',
        ]);
        $client_code = $request->input('client_code');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $cardlist = CardList::where('codigoCliente', $client_code)->whereIn('is_activated', [1, 2, 3])->select('Cuenta', 'Tarjeta')->first();
        $array = array();
        $array['Cuenta'] = $cardlist->Cuenta;
        $array['Tarjeta'] = $cardlist->Tarjeta;

        return $array;
    }

    public function getCardCreationStatus(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'client_code' => 'required',
        ]);
        $client_code = $request->input('client_code');

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->showCardsList());
        $cardlist = CardList::get();
        $user = User::where('cod_cliente', $client_code)->select('card_request')->first();
        $user_card_request = $user->card_request;


        $flag = false;
        $flag1 = false;

        if ($user_card_request == 1) {
            return 1;
        } else {
            $json_res = json_decode($res->original);
            foreach ($json_res as $value) {
                if ($value->CodigoCliente == $client_code) {
                    $flag1 = true;
                }
            }
            if ($flag1 == true) {
                return 1;
            } else {
                foreach ($cardlist as $value) {
                    if ($value->codigoCliente == $client_code) {
                        $flag = true;
                    }
                }
                if ($flag == true) {
                    return 1;
                } else {
                    return 0;
                }
            }
        }
    }


    public function sendTrxPayment(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data, [
            'tarjeta' => 'required',
            'operation_type' => 'required',
            'currency' => 'required',
            'description' => 'required',
            'transaction_amount' => 'required',
            // 'norecibo' => 'required',
            'username' => 'required',
        ]);
        $data_obj = (object) $data;
        // dd($data_obj);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->addTrxPayment($data_obj));
        $org_res        =   json_decode($res->original);
        $res_message    =   $org_res->InfoTran->ReturnMessage;
        dd($org_res);
    }


    public function downloadAccountStatement(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'account' => 'required',
        ]);
        $year = $request->input('year') != 0  ? $request->input('year') : 0;
        $month = $request->input('month') != 0 ? $request->input('month') : 0;
        $account = $request->input('account');
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()]);
        }
        $res = response(app(Versatec::class)->getAllAccountStatus($account, $year, $month));
        $data = json_decode($res->original);

        $pdf = PDF::loadView('testingview', compact('data'));
        return $pdf->download('pdf_file.pdf');
    }

    public function confirmPayment(Request $request)
    {

        if (isset($request->order_id)) {
            $user   =   User::where('email', $request->customer_email)->first();
            $card   =   CardList::where('user_id', $user->id)->first();
            $record     =   TransactionList::where('NoAutorizado', $request->order_id)->where('user_id', $user->id)->where('card_id', $card->id)->first();
            $status     =   $request->status;

            if (!$record) {
                $record =   TransactionList::create([
                    'FechaOrigen'   => $request->deposit_at,
                    'NoAutorizado'  => $request->order_id,
                    'CodTra'        => '10',
                    'Concepto'      => '20 - 000 - PAGOS',
                    'Descripcion'   => 'Quikipay Deposit',
                    'MtoTra'        => $request->quantity,
                    'user_id'       => $user->id,
                    'card_id'       => $card->id
                ]);
            }

            if (in_array($status, ['completed', 'COMPLETED'])) {
                $user   =   User::where('email', $request->customer_email)->first();
                $card   =   CardList::where('user_id', $user->id)->first();

                if ($user   && $card && $record->CodTra == '10') {
                    $data   =   (object)[
                        'tarjeta'           => $card->Tarjeta,
                        'operation_type'    => 20,
                        'currency'          => 1,
                        'description'       => 'Quikipay Deposit',
                        'transaction_amount' => $request->quantity,
                        'username'          => $user->username,
                        'norecibo'          => null
                    ];
                    $res            =   response(app(Versatec::class)->addTrxPayment($data));
                    $org_res        =   json_decode($res->original);
                    $res_message    =   $org_res->InfoTran->ReturnMessage;

                    if ($res_message ==  'Ok') {
                        $updated    =   $record->update([
                            'CodTra'    =>  '30'
                        ]);

                        if ($updated) {
                            return response()->json([
                                'success'   =>  true
                            ], 200);
                        }
                    }
                }
            } else {
                $updated    =   $record->update([
                    'CodTra'    =>  '10'
                ]);

                if ($updated) {
                    return response()->json([
                        'success'   =>  true
                    ], 200);
                }
            }
        }
    }

    public function associateAllCards()
    {
        $res    = response(app(Versatec::class)->showCardsList());
        $resp   = json_decode($res->original);

        foreach ($resp as $value)
        {
            $card           =   $value->Tarjeta;
            $account        =   $value->Cuenta;
            $client_code    =   $value->CodigoCliente;
            $idPlastico     =   $value->IdPlastico;
            $cif            =   $value->Cif;
            $user           =   User::where('cod_cliente', $client_code)->first();
            $message        =   null;

            if($user)
            {
                $user_id            =   $user->id;
                $username           =   $user->username;
                $cardjson           =   response(app(Versatec::class)->getCardInfo($card))->original;
                $cardinfo           =   json_decode($cardjson);
                $nombre_tarjeta     =   $cardinfo->NombreTh;
                $expiry_date        =   $cardinfo->Vencimiento;
                $tarjeta_digitos    =   $cardinfo->Tarjeta;
                $card_status        =   $cardinfo->Estado;
                $associate_response =   response(app(Versatec::class)->associateAccountToCard($card, $account, $client_code, $username));

                if($associate_response)
                {
                    $associate          =   $associate_response->original; 

                    if ($associate == 'Ok')
                    {

                        $card       =   CardList::updateOrCreate(
                        [
                            'idPlastico'        => $idPlastico
                        ],
                        [
                            'Cuenta'            =>  $account, 
                            'user_id'           =>  $user_id, 
                            'Tarjeta'           =>  $card, 
                            'IdPlastico'        =>  $idPlastico, 
                            'codigoCliente'     =>  $client_code, 
                            'Cif'               =>  $cif, 
                            'card_status'       =>  $card_status,
                            'expiry_date'       =>  $expiry_date,
                            'is_associated'     =>  1,
                            'nombre_tarjeta'    =>  $nombre_tarjeta,
                            'tarjeta_digitos'   =>  $tarjeta_digitos
                        ]);

                        if($card)
                        {
                            $message    =   'Card is Associated'.$client_code .'<br>';  
                        }
                        else
                        {
                            $message    =   'Card is Not Associated'.$client_code .'<br>';
                        }
                    }
                    else
                    {
                        $message    =   'User Exists but Card is Not OK'.$client_code .'<br>'; 
                    }
                }
                else
                {
                    $message    =   'There is no response from endpoint'.$client_code .'<br>'; 
                }
            }
            else
            {
                $message    =   'Neither User nor respective Card is existed'.$client_code .'<br>';
            }
                echo $message.$client_code .'<br>';
        }
    }

    public function activateAllCards()
    {
        $resp   = CardList::all();

        foreach ($resp as $value)
        {  
            $card           =   $value->Tarjeta; 
            $account        =   $value->Cuenta;
            $client_code    =   $value->codigoCliente;
            $idPlastico     =   $value->IdPlastico;
            $user           =   User::where('cod_cliente', $client_code)->first();
            $message        =   null;

            if($user)
            {   
                $user_id            =   $user->id;
                $username           =   $user->username;
                $activate_response  =   response(app(Versatec::class)->makeCardActive($account, $card, $client_code, $username));
                
                if($activate_response)
                {
                $activate           =   $activate_response->original;

                    if ($activate == 'Ok')
                    {
                        $card   =   CardList::where([

                            'user_id'       => $user_id,
                            'CodigoCliente' => $client_code,
                            'IdPlastico'    => $idPlastico,
                            ])->update([

                                'is_activated'      =>  1,
                                'card_status'       =>  'PLASTICO OK'
                            ]);
                        if($card)
                        {
                            $message    =   'Card is Activated'.$client_code .'<br>';  
                        }
                        else
                        {
                            $message    =   'Card is Not Activated'.$client_code .'<br>';
                        }
                    }
                    else
                    {
                        $message    =   'User Exists but Card is Not OK'.$idPlastico .'<br>'; 
                    }
                }
                else
                {
                    $message    =   'There is no response from endpoint'.$idPlastico .'<br>'; 
                }
            }
            else
            {
                $message    =   'Neither User nor respective Card is existed'.$idPlastico .'<br>';
            }
                echo $message.$client_code .'<br>';
        }
    }
}
