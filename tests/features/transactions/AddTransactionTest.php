<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\TransactionType;
use App\Category;
use App\Account;
use App\Loan;
use App\Transaction;
use Carbon\Carbon;

class AddTransactionTest extends TestCase
{
    use DatabaseMigrations;

    function setUp()
    {
        parent::setUp();
        $user = factory(App\User::class)->create();

        $this->getAccounts();

        $this->be($user);

        TransactionType::create(['name' => 'Income']);
        TransactionType::create(['name' => 'Expenses']);
        TransactionType::create(['name' => 'Loans']);
        TransactionType::create(['name' => 'Debts']);

        Category::create(['name' => 'Salary', 'transaction_type_id' => 1]);
        Category::create(['name' => 'Savings', 'transaction_type_id' => 1]);

        Category::create(['name' => 'Food', 'transaction_type_id' => 2]);
        Category::create(['name' => 'Games', 'transaction_type_id' => 2]);
    }

    /** @test */
    function user_can_add_expenses()
    {
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => 12.50,
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata'
        ]);

        $account = Account::find(1);

        $this->assertResponseStatus(200);

        $this->seeInDatabase('transactions', ['title' => 'pack empanadas + coca cola']);
        $this->seeInDatabase('transactions', ['date' => date('Y-m-d')]);

        $this->assertTrue($account->balance == 87.50);
    }

    /** @test */
    function user_cannot_add_expenses_without_title()
    {
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata'
        ]);

        $this->assertResponseStatus(422);

        $this->seeJson([
            'title' => ['The title field is required.']
        ]);
    }

    /** @test */
    function user_cannot_add_expenses_with_large_title()
    {
        $this->json('post', 'transactions', [
            'title' => 'Laravel also provides a variety of helpful tools to make it easier to test your database driven applications. First, you may use the seeInDatabase helper to assert that data exists in the database matching a given set of criteria. For example, if we would like to verify that there is a record in the users table with the email value of sally@example.com, we can do the following:',
            'account_id' => 1,
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata'
        ]);

        $this->assertResponseStatus(422);

        $this->seeJson([
            'title' => ['The title may not be greater than 50 characters.']
        ]);
    }

    /** @test */
    function user_can_add_expenses_without_description()
    {
        $this->json('post', 'transactions', [
            'title' => 'pack empanadas + coca cola',
            'account_id' => 1,
            'amount' => 12.50,
            'type' => 2,
            'category' => 3,
        ]);

        $this->assertResponseStatus(200);
    }

    /** @test */
    function user_cannot_add_expenses_with_amount_lesser_than_zero()
    {
        $this->json('post', 'transactions', [
            'title' => 'pack empanadas + coca cola',
            'account_id' => 1,
            'amount' => -10.0,
            'type' => 2,
            'category' => 3,
        ]);

        $this->assertResponseStatus(422);

        $this->seeJson([
            'amount' => ['The amount must be greater than 0.']
        ]);
    }

    /**
     * Marco
     *
     * @test
     */
    function user_cannot_add_expenses_without_amount()
    {
        $this->json('post', 'transactions', [
            'title' => 'pack empanadas + coca cola',
            'account_id' => 1,
            'type' => 2,
            'category' => 3,
        ]);

        $this->assertResponseStatus(422);

        $this->seeJson([
            'amount' => ['The amount field is required.']
        ]);
    }

    /**
     * Marco
     *
     * @test
     */
    function user_can_add_expenses_with_labels()
    {
        $this->disableExceptionHandling();
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'labels' => ['empanadas', 'metro', 'tk'],
            'description' => 'empanadas en metro de tarata'
        ]);

        $this->assertResponseStatus(200);

        $this->seeInDatabase('labels', ['name' => 'empanadas']);
        $this->seeInDatabase('labels', ['name' => 'metro']);
        $this->seeInDatabase('labels', ['name' => 'tk']);

        $transaction = Transaction::find(1);

        $this->assertTrue($transaction->labels()->count() == 3);
        $this->assertTrue($transaction->labels()->first()->name == 'empanadas');
    }

    /** @test */
    function user_can_add_expense_with_image()
    {
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata',
            ''
        ]);

        $this->assertResponseStatus(200);

        // To - do
    }

    /** @test */
    function user_cannot_add_transaction_with_category_of_other_transaction_type()
    {
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 1,
            'category' => 3,
            'description' => 'empanadas en metro de tarata',
        ]);

        $this->assertResponseStatus(422);

        $this->seeJson([
            'category' => ['The selected category is invalid.']
        ]);
    }

    /** @test */
    function user_can_add_transaction_with_custom_date_before_or_today()
    {
        $today = Carbon::today()->format('Y-m-d');
        $last_week = Carbon::today()->subWeek()->format('Y-m-d');

        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata',
            'date' => $last_week,
        ]);

        $this->assertResponseStatus(200);

        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata',
            'date' => $today,
        ]);

        $this->assertResponseStatus(200);

        $this->seeInDatabase('transactions', ['date' => $today]);

        $this->seeInDatabase('transactions', ['date' => $last_week]);
    }

    /** @test */
    function user_cannot_add_transaction_with_custom_date_after_today()
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata',
            'date' => $tomorrow,
        ]);

        $this->assertResponseStatus(422);

        $this->seeJson([
            'date' => ['The date must be a date before ' . $tomorrow . '.'],
        ]);
    }

    /** @test */
    function user_can_add_transaction_with_custom_hour_before_now()
    {
        $time = Carbon::now()->subMinutes(10);

        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata',
            'hour' => $time->format('H:i'),
        ]);

        $this->assertResponseStatus(200);

        $transaction = Transaction::find(1);

        $this->assertTrue($time->format('H:i') == $transaction->time);
    }

    /** @test */
    function user_cannot_add_transaction_with_custom_hour_after_now()
    {
        $time = Carbon::now()->addMinutes(10);

        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'pack empanadas + coca cola',
            'amount' => '12.50',
            'type' => 2,
            'category' => 3,
            'description' => 'empanadas en metro de tarata',
            'hour' => $time->format('H:i'),
        ]);

        $this->assertResponseStatus(422);

        $this->seeJson([
            'hour' => ['The hour should before the current time'],
        ]);
    }

    /** @test */
    function user_can_add_incomes()
    {
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'freelo',
            'amount' => 140.50,
            'type' => 1,
            'category' => 1
        ]);

        $account = Account::find(1);

        $this->assertResponseStatus(200);

        $this->seeInDatabase('transactions', ['title' => 'freelo']);

        $this->assertTrue($account->balance == 240.50);
    }

    /**
     * Loan - Prestamo es cuando prestas a alguien
     * Category is not mandatory
     * @test
     */
    function user_can_add_loan()
    {
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'loan to Juancito',
            'amount' => 99.40,
            'type' => 3,
            'description' => 'juancito buys many empanadas',
            'deadline' => '16/06/2017',
        ]);

        $this->assertResponseStatus(200);

        $this->seeInDatabase('loans', ['account_id' => 1]);
        $this->seeInDatabase('transactions', ['title' => 'loan to Juancito']);

        $loan    = Loan::find(1);
        $account = Account::find(1);

        $this->assertTrue($loan->account == $account);
        $this->assertTrue($loan->source->title == 'loan to Juancito');
    }

    /**
     * Debt - deuda es cuando te prestas de alguien
     * Category is not mandatory
     * @test
     */
    function user_can_add_debt()
    {
        $this->json('post', 'transactions', [
            'account_id' => 1,
            'title' => 'debt to Francisco',
            'amount' => 777.30,
            'type' => 4,
            'description' => 'debt for Francisco Party',
            'deadline' => '16/06/2017',
        ]);

        $this->assertResponseStatus(200);

        $this->seeInDatabase('loans', ['account_id' => 1]);
        $this->seeInDatabase('transactions', ['title' => 'debt to Francisco']);

        $loan    = Loan::find(1);
        $account = Account::find(1);

        $this->assertTrue($loan->account == $account);
        $this->assertTrue($loan->source->title == 'debt to Francisco');
    }
}
