<?php

function getClasses() {
    register_rest_route( 'resreq/main', '/get-classes', array(
        'methods' => 'GET',
        'callback' => 'getClassesCallback',
    ) );
}
add_action( 'rest_api_init', 'getClasses' );

function getClassesCallback($req = "empty") : String {

    $response = array( 'message' => $req );
    return rest_ensure_response( $response );
}


?>