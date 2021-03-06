<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Transaction;
use App\TransactionType;
use Carbon\Carbon;
use App\Account;
use App\Label;
use App\Loan;
use Auth;

class TransactionController extends Controller
{
    public function add()
    {
        $rules = [
            'title'    => 'required|max:50',
            'amount'   => 'required|gt:0',
            'category' => 'required_if:type,1,2|exists:categories,id,transaction_type_id,' . request('type'),
            'date'     => 'date_format:Y-m-d|before:' . Carbon::tomorrow()->format('Y-m-d'),
            'hour'     => 'before:'.Carbon::now()->format('H:i'),
            'deadline' => 'required_if:type:3,4',
        ];

        $messages = [
            'hour.before' => 'The hour should before the current time',
        ];

        $this->validate(request(), $rules, $messages);

        $account = Account::find(request('account_id'));

        $label_ids = [];

        if ($this->isValidAmount($account)) {
            $transaction = new Transaction;
            $transaction->title       = request('title');
            $transaction->account_id  = request('account_id');
            $transaction->type_id     = request('type');
            $transaction->category_id = request('category');
            $transaction->description = request('description');
            $transaction->amount      = request('amount');
            $transaction->date        = request('date', Carbon::now()->format('Y-m-d'));
            $transaction->time        = request('hour', Carbon::now()->format('H:i'));
            $transaction->user_id     = Auth::user()->id;

            $transaction->save();

            if (request('labels')) {
                $label_ids = array_map(function ($label) {
                    return Label::firstOrCreate(['name' => $label])->id;
                }, request('labels'));
                $transaction->labels()->attach($label_ids);
            }

            if ($this->isOutgoing()) {
                $account->balance -= request('amount');
            } else {
                $account->balance += request('amount');
            }

            $account->save();

            if ($this->isLoanable()) {
                $loan = Loan::create([
                    'account_id'     => $account->id,
                    'transaction_id' => $transaction->id,
                    'deadline'       => Carbon::createFromFormat('d/m/Y', request('deadline')),
                ]);
            }

            return response()->json([], 200);
        }

        return response()->json([], 400);
    }

    private function isLoanable()
    {
        return request('type') == TransactionType::LOAN || request('type') == TransactionType::DEBT;
    }

    private function isOutgoing()
    {
        return request('type') == TransactionType::EXPENSE || request('type') == TransactionType::LOAN;
    }

    private function isValidAmount($account)
    {
        if ($this->isOutgoing()) {
            return $account->balance >= request('amount');
        }

        return true;
    }
}
