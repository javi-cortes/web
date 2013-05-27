<?php
//Parser que coge los datos de ADV.

include 'simple_html_dom.php';
//para las funciones de la DB
require_once($_SERVER['DOCUMENT_ROOT'].'wordpress/wp-load.php' );

echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>'; // acentos 

//Llamamos al parser, se pueden poner condiciones de control / excepción comprobando que devolvería etc...
set_time_limit(120);//2minutos máximo de tiempo límite.
parsearADV();
header('Location: http://localhost/wordpress');//se le podría pasar un parámetro para dar info...



//Main function to call the parser, initially 3 pages.
function parsearADV()
{
    global $wpdb;
    $vaciarBD = true;//borramos los anteriores post/comentarios que hayan       
    if($vaciarBD)
    {
        //Borramos todos los post y comentarios que haya antes en la BD
        $queryResult = $wpdb->query( "DELETE FROM $wpdb->posts" );
        $queryResult = $wpdb->query( "DELETE FROM $wpdb->comments" );
    }
    
    $paginas = 5;
    $pagina = 'http://www.ascodevida.com/ultimos/p/';
    for ($i = 1; $i <= $paginas; $i++) 
    {
        //De esta forma va entrando en las diferentes páginas, la sintaxis es ...com/ultimos/p/numPagina
        parsearADV_Helper($pagina . "$i");
    }

}

//De una página de ADV dada, obtiene todos los adv's y sus comentarios.
function parsearADV_Helper($pagina)
{
    // Create DOM from URL 
    $html = file_get_html($pagina);

    // Find all box story, que son las historietas de adv
    foreach($html->find('div[class=box story]') as $element)
    {   
        $historia = $element->find('a[class=advlink]');
        $historiaLink = $historia[0]->href; // para obtener el link de la historia.
        $historiaTexto = $historia[0]->plaintext; // obtenemos el texto

        //Para obtener los votos hacemos un foreach de los spans, y miramos cada caso
        //Regular Expressions
        $RE_comentariosCount = '/class="comments"/';
        $RE_Votos = '/class="vote"/';
        $RE_NumVotos = '/[0-9]+/';
        $RE_ADV = '/Vaya asco de vida/';
        $RE_HaberloPensado = '/Haberlo pensado/';
        $RE_MenudaChorrada = '/Menuda chorrada/';

        foreach($element->find('span') as $spans )
        {
            //Número de comentarios
                $matchingCount = preg_match($RE_comentariosCount, $spans->innertext, $matches);
            if ( $matchingCount > 0 )
            {
                //echo "si, encontrado un class=comments" . '</br>';
                //echo $matches[0].'</br>';
                $comentariosCount = $spans->plaintext;
                //echo 'Num comentarios = ' . $comentariosCount.'</br>';
                //saltamos a la siguiente iteración, no tiene por qué pasar nada pero por si acaso
                continue;
            }

            //Votos
            $matchingCount = preg_match($RE_Votos, $spans->innertext, $matches);
            if ( $matchingCount > 0 )
            {
                //Votos ADVs
                $matchingCount = preg_match($RE_ADV, $spans->plaintext, $matches);
                if ($matchingCount > 0)
                {
                    $matchingCount = preg_match($RE_NumVotos, $spans->plaintext, $matches);
                    if($matchingCount > 0)
                    {
                        $votosADV = $matches[0];
                        //echo 'numero de advs = '.$votosADV. '</br>';
                        continue;
                    }
                }  
                //Votos Haberlo Pensado
                $matchingCount = preg_match($RE_HaberloPensado, $spans->plaintext, $matches);
                if ($matchingCount > 0)
                {
                    $matchingCount = preg_match($RE_NumVotos, $spans->plaintext, $matches);
                    if($matchingCount > 0)
                    {
                        $votosHaberloPensado = $matches[0];
                        //echo 'numero de haberlo pensado = '.$votosHaberloPensado. '</br>';
                        continue;
                    }
                }  
                //Votos Menuda Chorrada
                $matchingCount = preg_match($RE_MenudaChorrada, $spans->plaintext, $matches);
                if ($matchingCount > 0)
                {
                    $matchingCount = preg_match($RE_NumVotos, $spans->plaintext, $matches);
                    if($matchingCount > 0)
                    {
                        $votosMenudaChorrada = $matches[0];
                        //echo 'numero menuda chorrada = '.$votosMenudaChorrada. '</br>';
                        continue;
                    }
                }  
            }
        }

        $historiaComentarios = getComentarios($historiaLink);

        //blanco entre advs
        //echo "<br/><br/><br/><br/><br/>";
        //Introducir la información del ADV en concreto en la base de datos
        $result = insertarEnBD($historiaTexto, $comentariosCount, $votosADV, $votosHaberloPensado, $votosMenudaChorrada, $historiaComentarios);
        if ($result == -1 )
        {
            echo "Ha habido algún problema al insertar el post en la base de datos";
        }
    }
}

//Obtiene los comentarios de un ADV.
//@param = la url 
//@return = el comentario en forma de string.
function getComentarios($historiaLink)
{
    // Create DOM from URL 
    $htmlHistoria = file_get_html($historiaLink);
    // Recorrer el html buscando guardando todos los comentarios en un array
    unset($comentarios);
    $comentarios = array();
    $i = 0;
    foreach($htmlHistoria->find('div[class=userbox]') as $element)
    { 
        //se mira el next_sibling() ya que el comentario está entre <p> y está justo después del div de class="userbox"
        if ( $element->next_sibling() != NULL)
        {
            //echo "COMENTARIOOO ". $element->next_sibling()->plaintext . "</br></br></br>";
            $comentarios[$i] = $element->next_sibling()->plaintext;//array_push los va metiendo al final
            $i++;
        }
    }
    //echo "coimentarios antes = ".count($comentarios);
    return $comentarios;
}

function insertarEnBD($texto, $numComentarios, $votosADV, $votosHaberloPensado, $votosMenudaChorrada, array $comentarios)
{
    global $wpdb;

    //Introducimos la info. como un nuevo post en la bd ( pensar en poner la fecha / autor etc...
    $wpdb->insert( $wpdb->posts, array(     'post_content' => $texto,
                                            'comment_count' => $numComentarios,
                                            'post_like' => $votosADV,
                                            'post_nolike' => $votosHaberloPensado,
                                            'post_dontcare' => $votosMenudaChorrada
                                            )); 
    
    //Para insertar el comentario, hay que linkarlo al post, para eso buscamos la id del que acabamos de meter, al ser incremental, será el más grande :D
    $post = $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = (SELECT MAX(ID) FROM $wpdb->posts) " ) );
    if (isset($post) )
    {
        foreach($comentarios as $comentario)
        { 
            $wpdb->insert( $wpdb->comments, array(  //'comment_author' => $first_comment_author,
                                               // 'comment_author_email' => '',
                                                //'comment_author_url' => $first_comment_url,
                                                //'comment_date' => $now,
                                                //'comment_date_gmt' => $now_gmt,
                                                    'comment_content' => $comentario,
                                                    'comment_post_ID' => $post->ID
                                                     ));
        }
    }
    else 
    {
        return -1;
    }
    
    return 0;
}

function testBD()
{
    global $wpdb;

    //Para insertar el comentario, hay que linkarlo al post, para eso buscamos la id del que acabamos de meter, al ser incremental, será el más grande :D
    //$postID->ID = $wpdb->get_row( $wpdb->prepare( "SELECT MAX(ID) FROM $wpdb->posts" ) ); 
    $post = $wpdb->get_row( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID = (SELECT MAX(ID) FROM $wpdb->posts) " ) );
    
    //echo "postid = ".$_post->ID."</br>";
    //echo "postid = ".$_post->post_like."</br>";
}



?>

