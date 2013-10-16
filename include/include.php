<?php
// CONSTANTES
if ( 'dev' == $this->mode ) {
	define("ENDERECO_BASE", "https://dev.akatus.com");
} else {	
	define("ENDERECO_BASE", "https://www.akatus.com");
}
define("ENDERECO", ENDERECO_BASE."/api/v1/meios-de-pagamento.xml");
define("CARRINHO", ENDERECO_BASE."/api/v1/carrinho.xml");
define("PARCELAMENTO", ENDERECO_BASE."/api/v1/parcelamento/simulacao.xml");

// Envia requisição
function http_request($url, $post_data, $post = true ){

	$sessao_curl = curl_init();
	curl_setopt($sessao_curl, CURLOPT_URL, $url);

/*
	curl_setopt($sessao_curl, CURLOPT_DNS_USE_GLOBAL_CACHE, false );
	curl_setopt($sessao_curl, CURLOPT_DNS_CACHE_TIMEOUT, 2 );
	curl_setopt($sessao_curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($sessao_curl, CURLOPT_SSL_VERIFYPEER, false);
*/
/**/
	curl_setopt($sessao_curl, CURLOPT_FAILONERROR, false);
	//  CURLOPT_SSL_VERIFYPEER
	//  verifica a validade do certificado
	curl_setopt($sessao_curl, CURLOPT_SSL_VERIFYPEER, false);

	//  CURLOPT_CONNECTTIMEOUT
	//  o tempo em segundos de espera para obter uma conexão
	curl_setopt($sessao_curl, CURLOPT_CONNECTTIMEOUT, 10);

	//  CURLOPT_TIMEOUT
	//  o tempo máximo em segundos de espera para a execução da requisição (curl_exec)
	curl_setopt($sessao_curl, CURLOPT_TIMEOUT, 40);

	//  CURLOPT_RETURNTRANSFER
	//  TRUE para curl_exec retornar uma string de resultado em caso de sucesso, ao
	//  invés de imprimir o resultado na tela. Retorna FALSE se há problemas na requisição
	curl_setopt($sessao_curl, CURLOPT_RETURNTRANSFER, true);

	if ( $post ) {
	curl_setopt($sessao_curl, CURLOPT_POST, true);
	curl_setopt($sessao_curl, CURLOPT_POSTFIELDS, $post_data );
	}
/**/

	
	$resultado = curl_exec($sessao_curl);

    $error = curl_error($sessao_curl);
	$XML_erro = '<?xml version="1.0" encoding="UTF-8" ?>
<resposta>
  <status>erro</status>
  <descricao>(' . $errno . ') ' . $error .'</descricao>
</resposta>';
	curl_close($sessao_curl);

	if ( $resultado )	{
		return $resultado;
	} else {
		return $XML_erro;
	}
}
?>