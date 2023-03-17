<p align="center"><h1>Laravel Stripe Subscription</h1></p>

## Steps to clone laravel project

- Clone your project.
- Go to the folder application using cd command on your cmd or terminal
- Run composer install on your cmd or terminal.
- Copy .env.example file to .env in the root folder using command <i> cp .env.example .env </i>
- Open your .env file and change the database name (DB_DATABASE) to whatever you have, username (DB_USERNAME) and password (DB_PASSWORD) field correspond to your configuration..
- Run php artisan key:generate.
- Run php artisan migrate.
- Run php artisan serve
- Go to http://localhost:8000/

## Follow the steps below:

- Create New Project
- Install Packages for Stripe-php Using Composer
- Create Stripe account
- Add Products on stripe
- Add Webhook end point
- Configure the package
- Create Routes
- Run the app
- Run APIs on Postman (https://api.postman.com/collections/18476697-2b8e4239-4264-4c5b-9ead-217e9242ef10?access_key=PMAT-01GVQH1D3EWJP761ESNDPP9J2D)

<ol>
    <li><h5>Create a new project</h5></li>
        <p>Create a new project with the command as below.</p>
        <p><i>composer create-project laravel/laravel-stripe-subscription</i></p>
        <p>After the new project has been created, go to your project directory.</p>
        <p><i>cd laravel-stripe-subscription</i></p>
    <li><h5>Install Packages for Stripe-php Using Composer</h5></li>
        <p>Run the following command.</p>
        <p><i>composer require stripe/stripe-php</i></p>
    <li><h5>Create Stripe account and get API keys</h5></li>
        <p>Create a Stripe account and login to the dashboard. Navigate through the Developers -> API keys menu to get the API keys. There are two type of standard API keys named secret key and publishable key. The secret key will be masked by default which has to be revealed by clicking reveal key token control explicitly.</p>
        <img src="https://media.stripe.com/6050469652bc9a2aa6ea39ef25bd4980a723ad2a.png" alt="img" >
        <img src="https://techsolutionstuff.com/adminTheme/assets/img/stripe_payment_gateway_api_key.png" alt="img">
    <li><h5>Add Products on stripe</h5></li>
        <p>Create a Stripe account and login to the dashboard. Navigate through the Developers -> Add Product. There are two types of products, recurring and One time. Choose recurring one for stripe subscriptions.</p>
    <li><h5>Add Webhook end-point</h5></li>
        <ul>
            <li>Navigate through the Developers -> Webhooks menu to add webhook end-point</li>
                <img src="https://cdn.wpsimplepay.com/wp-content/uploads/2022/12/wp-simple-pay-add-endpoint-1536x994.png" alt="img" >
            <li>Click Add endpoint</li>
            <li>Add your webhook endpoint’s HTTPS URL in Endpoint URL (ex. https://<your-website>/<your-webhook-endpoint>)</li>
                <img src="https://cdn.wpsimplepay.com/wp-content/uploads/2022/12/wp-simple-pay-add-endpoint-settings-1536x1308.png" alt="img">
            <li>Select the event types you’re currently receiving in your local webhook endpoint in Select events. You now will need to add the specific events to listen to by clicking the button labeled +Select events.</li>
            <li>Click Add endpoint</li>
            <li>Configuring the Webhook Signing Secret, To do so, retrieve your endpoint’s secret from your Dashboard’s webhooks settings. Select the added endpoint for which you want to obtain the secret, then click the Reveal button.</li>
                <img src="https://cdn.wpsimplepay.com/wp-content/uploads/2022/12/stripe-reveal-secret-1536x324.png" alt="img">
        </ul>
    <li><h5>Configure the package</h5></li>
        <p>After the package installation is complete, you open your project and add the key and secret key that you got in the .env file.</p>
        <p>
        STRIPE_KEY=pk_test_xxxxxx<br>
        STRIPE_SECRET=sk_test_xxxxxx<br>
        STRIPE_WEBHOOK_SECRET=whsec_xxxxxx
        </p>
    <li><h5>Create Routes</h5></li>
        <p>Now we need to create an application route that we will test the application test transaction on. Open the route/api.php application route file and add the new routes</p>
        <p>Create Controller</p>
        <p><i>php artisan make:controller api/StripeController</i></p>
        <p>Run database migrations</p>
        <p><i>php artisan migrate</i></p>
        <p>Run database seeder</p>
        <p><i>php artisan db:seed --class=PlansSeeder</i></p>
    <li><h5>Run the app</h5></li>
        <p>Stripe subscription integration complete. Now we need to make a transaction. Run the Laravel server using the Artisan command below.</p>
        <p><i>php artisan serve</i></p>
    <p>Thus this tutorial I provide, hopefully useful.</p>
    <p>Thanks.</p>  
</ol>

