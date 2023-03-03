<h1>Laravel Stripe Subscription</h1>
<h4>Follow the steps below:</h4>
<ul>
    <li> Create New Project </li>
    <li> Install Packages for Stripe-php Using Composer </li>
    <li> Create Stripe account </li>
    <li> Add Products on stripe </li>
    <li> Configure the package </li>
    <li> Create Routes </li>
    <li> Create blade file to create payment button </li>
    <li> Run the app </li>
</ul>

<ol>
    <li><h5>Create a new project</h5></li>
        <p>Create a new project with the command as below.</p>
        <p><i>composer create-project laravel/laravel-stripe-subscription --jet</i></p>
        <p>After the new project has been created, go to your project directory.</p>
        <p><i>cd paypal</i></p>
    <li><h5>Install Packages for Stripe-php Using Composer</h5></li>
        <p>Run the following command.</p>
        <p><i>composer require stripe/stripe-php</i></p>
    <li><h5>Create Stripe account and get API keys</h5></li>
        <p>Create a Stripe account and login to the dashboard. Navigate through the Developers -> API keys menu to get the API keys. There is two type of standard API keys named secret key and publishable key. The secret key will be masked by default which has to be revealed by clicking reveal key token control explicitly.</p>
        <img src="https://techsolutionstuff.com/adminTheme/assets/img/stripe_payment_gateway_api_key.png" alt="img">
    <li><h5>Add Products on stripe</h5></li>
        <p>Create a Stripe account and login to the dashboard. Navigate through the Developers -> Add Product. There are two types of products, recurring and One time. Choose recurring one for stripe subscriptions.</p>
    <li><h5>Configure the package</h5></li>
        <p>After the package installation is complete, you open your project and add the key and secret key that you got in the .env file.</p>
        <p>
        STRIPE_KEY=pk_test_xxxxxx<br>
        STRIPE_SECRET=sk_test_xxxxxx
        </p>
    <li><h5>Create Routes</h5></li>
        <p>Now we need to create an application route that we will test the application test transaction on. Open the route/web.php application route file and add the new routes</p>
        <p>Create Controller</p>
        <p><i>php artisan make:controller PlanController</i></p>
        <p>Run database migrations</p>
        <p><i>php artisan migrate</i></p>
        <p>Run database seeder</p>
        <p><i>php artisan db:seed --class=PlansSeeder</i></p>
    <li><h5>Create blade file to create payment button</h5></li>
        <p>create a view that will direct to process the transaction. Create blade view resources/views/transaction.blade.php file</p>
    <li><h5>Run the app</h5></li>
        <p>Stripe subscription integration complete. Now we need to make a transaction. Run the Laravel server using the Artisan command below.</p>
        <p><i>php artisan serve</i></p>
    <p>Thus this tutorial I provide, hopefully useful.</p>
    <p>Thanks.</p>  
</ol>





