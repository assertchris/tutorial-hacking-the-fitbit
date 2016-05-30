<?php

$app->get("/", function () {
    return view("dashboard");
});

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Subscriber\Oauth\Oauth1;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Contracts\Factory;

$manager = $app->make(Factory::class);

$fitbit = $manager->with("fitbit")->stateless();
$twitter = $manager->with("twitter");

$app->get("/auth/fitbit", function () use ($fitbit) {
    return $fitbit->redirect();
});

$app->get("/auth/fitbit/callback", function () use ($fitbit) {
    $user = $fitbit->user();

    Cache::forever("FITBIT_TOKEN", $user->token);
    Cache::forever("FITBIT_USER_ID", $user->id);

    return redirect("/");
});

$app->get("/auth/twitter", function () use ($twitter) {
    return $twitter->redirect();
});

$app->get("/auth/twitter/callback", function () use ($twitter) {
    $user = $twitter->user();

    Cache::forever("TWITTER_TOKEN", $user->token);
    Cache::forever("TWITTER_TOKEN_SECRET", $user->tokenSecret);

    return redirect("/");
});

$app->get("twitter/fetch", function() {
    $middleware = new Oauth1([
        "consumer_key" => getenv("TWITTER_KEY"),
        "consumer_secret" => getenv("TWITTER_SECRET"),
        "token" => Cache::get("TWITTER_TOKEN"),
        "token_secret" => Cache::get("TWITTER_TOKEN_SECRET"),
    ]);

    $stack = HandlerStack::create();
    $stack->push($middleware);

    $client = new Client([
        "base_uri" => "https://api.twitter.com/1.1/",
        "handler" => $stack,
    ]);

    $latest = Cache::get("TWITTER_LATEST", 0);

    $response = $client->get("direct_messages.json", [
        "auth" => "oauth",
        "query" => [
            "since_id" => $latest,
        ],
    ]);

    // header("content-type: application/json");
    // print $response->getBody();

    $response = (string) $response->getBody();
    $response = json_decode($response, true);

    foreach ($response as $message) {
        if ($message["id"] > $latest) {
            $latest = $message["id"];
        }
    }

    Cache::forever("TWITTER_LATEST", $latest);

    // header("content-type: application/json");
    // print json_encode($response);

    if (count($response)) {
        $token = Cache::get("FITBIT_TOKEN");
        $userId = Cache::get("FITBIT_USER_ID");

        $client = new Client([
            "base_uri" => "https://api.fitbit.com/1/",
        ]);

        $response = $client->get("user/-/devices.json", [
            "headers" => [
                "Authorization" => "Bearer {$token}"
            ]
        ]);

        $response = (string) $response->getBody();
        $response = json_decode($response, true);

        $id = $response[0]["id"];

        $endpoint = "user/{$userId}/devices/tracker/{$id}/alarms.json";

        $response = $client->post($endpoint, [
            "headers" => [
                "Authorization" => "Bearer {$token}"
            ],
            "form_params" => [
                "time" => date("H:iP", strtotime("+1 minute")),
                "enabled" => "true",
                "recurring" => "false",
                "weekDays" => [strtoupper(date("l"))],
            ],
        ]);

        header("content-type: application/json");
        print $response->getBody();
    }
});
