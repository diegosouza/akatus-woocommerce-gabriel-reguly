<?php
/*
Plugin Name: WooCommerce Akatus Gateway
Plugin URI: http://omniwp.com.br/
Description: Adiciona a opção de pagamento pela Akatus ao WooCommerce 
Version: 2.0.1
Author: omniWP
Author URI: http://omniwp.com.br

	Copyright: © 2012 omniWP
	License: GNU General Public License v3.0
	License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'akatus_gateway_plugin_action_links' );

/**
 * Add a direct link to plugin settings 
 */
function akatus_gateway_plugin_action_links( $links ) {
	$settings_link = array( '<a href="' . admin_url( 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_akatus_gateway') . '">Configuração</a>' );		
	return array_merge( $settings_link, $links );
}

add_action('plugins_loaded', 'woocommerce_akatus_init', 0);


function woocommerce_akatus_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	/**
 	 * Gateway class
 	 */
	class WC_akatus_gateway extends WC_Payment_Gateway {
	
		public function __construct() { 
			global $woocommerce;

	        $this->id			= 'akatus_gateway';
	        $this->method_title = 'Akatus';
			$this->icon 		= plugins_url( 'i/akatus.png' , __FILE__ );
            $this->has_fields   = true;

			
			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();
			
			// Define user set variables
			$this->title 		   = $this->settings['title'];
			$this->description 	   = $this->settings['description'];
			$this->email           = $this->settings['email'];
			$this->token           = $this->settings['token'];
			$this->chave           = $this->settings['chave'];
			$this->mode 		   = $this->settings['mode'];


           // actions
			add_action( 'woocommerce_api_wc_akatus_gateway', array( $this, 'resposta_nip' ) );
			add_action( 'woocommerce_update_options_payment_gateways_akatus_gateway' , array( $this, 'process_admin_options' ) );

			add_action( 'woocommerce_thankyou_akatus', array( $this, 'thank_you_page' ) );

			// js and css
		    wp_register_style( 'jquery-ui-css', plugins_url( '/js/theme/jquery.ui.all.css', __FILE__) );
		    wp_enqueue_style( 'jquery-ui-css' );
			wp_enqueue_script( 'jquery-ui' );
			wp_enqueue_script( 'jquery-ui-accordion' );

			$this->enabled = ( 'yes' == $this->settings['enabled'] ) && $this->testa_dados_akatus();
		} 

		/**
		 * Resposta NIP, atualizar status
		 **/
		function resposta_nip() {
			// vem o token correto ?
			if ( $this->token == $this->get_request( 'token' ) )  {
				// atualizar a ordem 
				$order = new WC_Order( $this->get_request( 'referencia' ) );
				if ( 'Aprovado' == $this->get_request( 'status' ) ) {
					// estava esperando, pode completar
					if ( 'on-hold' == $order->status ) {
						$order->update_status( 'processing', 'Pagamento Akatus completo,' );
					} else {
						// status desconhecido, somente registra que pagamento está completo
						$order->add_order_note( 'Pagamento Akatus completo' );
					}
				} elseif ( 'Cancelado' == $this->get_request( 'status' ) ) {
					// estava esperando, pode cancelar
					if ( 'on-hold' == $order->status ) {
						$order->update_status( 'cancelled', 'Pagamento Akatus cancelado,' );
					} else {
						// status desconhecido, somente registra que pagamento está cancelado
						$order->add_order_note( 'Pagamento Akatus cancelado' );
					}
				} elseif ( 'Em Análise' == $this->get_request( 'status' ) ) {
						$order->add_order_note( 'Pagamento Akatus em análise de risco' );
				} 
		
			}
		}

		/**
	     * Initialise Gateway Settings Form Fields
	     */
	    function init_form_fields() {
		
	    	$this->form_fields = array(
				'enabled' => array(
								'title' => 'Habilitar/Desabilitar', 
								'type' => 'checkbox', 
								'label' => 'Ativar Akatus', 
								'default' => 'yes'
							), 
				'title' => array(
								'title' => 'Título', 
								'type' => 'text', 
								'description' => 'Essa opção controla o título mostrado ao cliente quando ele está no checkout.', 
								'default' => 'Akatus + Fácil Pagar'	
							),
				'description' => array(
								'title' => 'Descrição', 
								'type' => 'textarea', 
								'description' => 'Essa opção controla a descrição mostrada ao cliente quando ele está no checkout.', 
								'default' => 'Pague usando o gateway da Akatus'
							),
                'mode'      => array(
                                'title' => 'Modo de operação',
                                'type' => 'select',
                                'options' => array( 
												'dev' => 'Teste', 
												'prod' => 'Produção'
								),
                                'description' => 'Selecione modo de operação produção <strong>somente após ter certeza que sua loja está pronta</strong>.',
								'default' => 'dev'
                            ),
				'email' => array(
								'title' => 'Email', 
								'description' => 'Deve ser o mesmo utilizado para fazer login na Akatus.', 
								'type' => 'text', 
								'default' => ''
							),
				'token' => array(
								'title' => 'Token NIP', 
								'description' => '<br />Este token se encontra na sua conta Akatus em <a href="https://www.akatus.com/painel/cart/token" target="akatus">Integração -> Chaves de Segurança -> <strong>Token NIP</strong></a>', 
								'type' => 'text', 
								'default' => ''
							),
				'chave' => array(
								'title' => 'Chave da API', 
								'description' => '<br />Este token se encontra na sua conta Akatus em <a href="https://www.akatus.com/painel/cart/token" target="akatus">Integração -> Chaves de Segurança -> <strong>API Key</strong></a>', 
								'type' => 'text', 
								'default' => ''
							)
				);
	    } // End init_form_fields()
	    
		/**
		 * Admin Panel Options 
		 */
		public function admin_options() {
?>

<h3>Akatus</h3>
<?php
			if ( get_option('woocommerce_currency') != 'BRL' ) {
				$this->enabled = false;
?>
<div class="inline error">
	<p><strong>Erro de moeda.</strong>: A moeda selecionada não é o Real.</p>
</div>
<?php
			} else {
				if (  ! $this->testa_dados_akatus() ) { 
					$this->enabled = false;
	?>
<div class="inline error">
	<p><strong>Gateway Desativado</strong>: Você deve especificar o email cadastrado juntamente à Akatus.</p>
</div>
<p>Não tem uma conta na Akatus? <a href="https://www.akatus.com/users/sign_up" target="_blank">Crie uma gratuitamente</a>.</p>
<?php
				}
?>
<table class="form-table">
	<?php
	    		// Generate the HTML For the settings form.
	    		$this->generate_settings_html();
?>
</table>
<?php
				if ( $this->enabled ) {
					$this->mostrar_opcoes_akatus();
				}
			}
	    } // End admin_options()
		
		/**
		 * Payment form on checkout page or on pay page
		 */
 		function payment_fields() {
			global $woocommerce;
			$show_fields = false;
			if ( $this->description )  {
				echo wpautop( wptexturize( $this->description ) );
			}
			if ( $woocommerce->cart->needs_payment() ) {
				// Checkout: Pay for order in cart
				$valor =  $woocommerce->cart->total;
				$show_fields = 'cart';
			} else {
				if ( isset($_GET['pay_for_order']) && isset($_GET['order']) && isset($_GET['order_id']) ) {
					// Pay for existing order
					$order_key = urldecode( $_GET['order'] );
					$order_id = (int) $_GET['order_id'];
					$order = new WC_Order( $order_id );
					if ( $order->id == $order_id 
							&& $order->order_key == $order_key 
							&& in_array( $order->status, array( 'pending', 'failed' ) ) ) {
						$valor = $order->order_total;
						$show_fields = 'order';
?>
<script>
jQuery( document ).ready( function() {
	jQuery( 'body' ).trigger( 'updated_checkout' );
});
</script>
<?php						
					}
				}
			}
			if ( false != $show_fields ) {
				$this->ler_opcoes_akatus();
?>
<script>
jQuery(document).ready(function(){
	jQuery( document ).on( 'updated_checkout', function() {
        jQuery("#accordion").accordion({
            autoHeight: false,
			resize: true,
			refresh: true
        });
    });
});
</script>
<div id="accordion">
	<?php
				if ( 'yes' == $this->boleto_akatus ) {
?>
	<h3 style="padding-left:1.5em;">Boleto bancário</h3>
	<div>
		<input type="radio" name="formaPagamentoAkatus" value="boleto" id="meio_boleto"  />
		<label for="meio_boleto"> R$ <?php echo number_format( $valor, 2, ',', '.' ) ?></label>
	</div>
	<?php
				}
				if ( sizeof( $this->opcoes_tefs_akatus ) ) {
?>
	<h3 style="padding-left:1.5em;">Transferências</h3>
	<div>
		<?php
					foreach( $this->opcoes_tefs_akatus as $key => $value ) {
?>
		<input type="radio" name="formaPagamentoAkatus" value="<?php echo $key ?>" id="meio_<?php echo $key ?>"  />
		<label for="meio_<?php echo $key ?>"><?php echo $value ?> R$ <?php echo number_format( $valor, 2, ',', '.' ) ?></label>
		<br />
		<?php
					}			
?>
	</div>
	<?php				
				}
				if ( sizeof( $this->opcoes_cartoes_akatus ) ) {
					$nro_maximo_de_parcelas = 999; 
					$valor_minimo_por_parcela = 5;
				
?>
	<h3 style="padding-left:1.5em;">Cartões</h3>
	<div>
		<?php
					foreach( $this->opcoes_cartoes_akatus as $key => $value ) {
						$k = 1;	
						$id = 'meio_' . $key .'_' . $k;
?>
		<input type="radio" name="formaPagamentoAkatus" value="<?php echo $key ?>" id="<?php echo $id ?>"  />
		<label for="<?php echo $id ?>"><?php echo $value ?></label>
		<br />
		<?php
						// calcular nro de parcelas, respeitando a parcela mínima 
						$i = $this->parcelas_cartoes_akatus[ $key ];
						if ( $nro_maximo_de_parcelas > $i ) {
							if ( ( $valor / $i ) < $valor_minimo_por_parcela ) {
								$i--;
								while ( $i > 0 && ( ( $valor / $i ) < $valor_minimo_por_parcela ) )  {
									$i--;
								}
							}
							if ( $i < $nro_maximo_de_parcelas ) {
								$nro_maximo_de_parcelas = $i;
							}
						}

				}		
				$options = '<option value="1">à vista R$ ' . number_format( $valor, 2, ',', '.' ) . '</option>';
				for ( $j = 2; $j <= $nro_maximo_de_parcelas; $j++ ) {
					$options .= '
						<option value="' . $j . '" >Crédito ' .  $j . 'x R$ ' . number_format( $valor/$j, 2, ',', '.' ) . '</option>';
				}
				$month_options = '';
				$selected_month = date( 'm' );
				for ( $j = 1; $j <= 12; $j++ ) {
					$k = sprintf( '%02d', $j );
					if ( $k == $selected_month ) {
						$month_options .= '
							<option value="' . $k  . '" selected="selected">' . $k . '</option>';
					} else {
						$month_options .= '
							<option value="' . $k  . '">' . $k . '</option>';
					}
				}
				$year = date( 'Y' );
				$year_options = '
							<option value="' . $year . '" selected="selected">' . $year . '</option>';
				for ( $j = 1;  $j <= 7; $j++ ) {
					$year++;
					$year_options .= '
							<option value="' . $year . '">' . $year  . '</option>';
				}
					
?>
		<ul>
			<li>
				<label for="parcelas">Forma de pagamento</label>
				<select name="parcelas" id="parcelas">
					<?php echo $options; ?>
				</select>
			</li>
			<li>
				<label for="card_number">Número do cartão</label>
				<input type="text" name="card_number" id="card_number" maxlength="20" size="21">
			</li>
			<li>
				<label for="expiry_month">Data de validade</label>
				<select name="expiry_month">
					<?php echo $month_options; ?>
				</select>
				<select name="expiry_year">
					<?php echo $year_options; ?>
				</select>
			</li>
			<li>
				<label for="cvv">CVV</label>
				<input type="text" name="cvv" id="cvv" maxlength="3" size="4">
			</li>
			<li>
				<label for="name_on_card">Nome do portador</label>
				<input type="text" name="name_on_card" id="name_on_card">
			</li>
			<li>
				<label for="cpf">CPF do portador</label>
				<input type="text" name="cpf" id="cpf" maxlength="14" size="15">
			</li>
		</ul>
	</div>
	<?php			
				}

			}
?>
</div>
<?php			
		}
		/**
		 * Validate payment form fields
		**/
		public function validate_fields() {
			global $woocommerce;
			$valid = false;
			if ( 'akatus' == $this->get_request('payment_method') ) { 
				$valid = true;
				$forma_pagamento = $this->get_request('formaPagamentoAkatus');
				if ( empty( $forma_pagamento ) ) {
					$woocommerce->add_error( 'Selecione a forma de pagamento desejada' );
					$valid = false;
				} elseif ( strstr( $forma_pagamento, 'cartao' ) ) {
					$numeroCartao = preg_replace('/[^0-9]+/', '', $this->get_request('card_number') );
					$dataCartao = $this->get_request('expiry_year') . $this->get_request('expiry_month');
					$cvvCartao = $this->get_request('cvv');
					$nomeCartao = $this->get_request('name_on_card');
					$cpf = $this->get_request('cpf');
					if ( empty( $numeroCartao ) ) {
						$woocommerce->add_error( 'Informe o número do cartão' );
						$valid = false;
					} else {
						function mod10_check( $number ) {
							$sum = 0;
							$strlen = strlen($number);
							if($strlen < 13){ return false; }
							for($i=0;$i<$strlen;$i++){
								$digit = substr($number, $strlen - $i - 1, 1);
								if($i % 2 == 1){
									$sub_total = $digit * 2;
									if($sub_total > 9){
										$sub_total = 1 + ($sub_total - 10);
									}
								} else {
									$sub_total = $digit;
								}
								$sum += $sub_total;
							}
							if($sum > 0 && $sum % 10 == 0){ 
								return true; 
							}
							return false;
						}
						if ( ! mod10_check( $numeroCartao ) ) {
							$woocommerce->add_error( 'O número informado do cartão está incorreto' );
							$valid = false;
						}
					}
					if ( $dataCartao < date( 'Yn') ) {
						$woocommerce->add_error( 'A data de validade do cartão está vencida' );
						$valid = false;
					}
					if ( empty( $cvvCartao ) ) {
						$woocommerce->add_error( 'Informe o número CVV do cartão' );
						$valid = false;
					}
					if ( empty( $nomeCartao ) ) {
						$woocommerce->add_error( 'Informe o nome do portador ' );
						$valid = false;
					}
					if ( empty( $cpf ) ) {
						$woocommerce->add_error( 'Informe o CPF do portador' );
						$valid = false;
					}
				}
					
			} 		
			return $valid;
		}
		
		/**
		 * Process the payment and return the result
		 **/
		function process_payment( $order_id ) {
			require_once 'include/include.php';
			global $woocommerce;
			$order = new WC_Order( $order_id );

			$forma_pagamento = $this->get_request( 'formaPagamentoAkatus' );
			if ( strstr( $forma_pagamento, 'cartao' ) ) {
				$forma_pagamento .= '_' . $this->get_request( 'parcelas' );
			} elseif ( strstr( $forma_pagamento, 'tef' ) ) {
				$forma_pagamento .= '_1';
			}
			
			$xml_carrinho = $this-> XML_header() 
				 . $this->XML_pagamento( $order );
			$resposta_api = http_request( CARRINHO, $xml_carrinho );
			$xml_resposta = simplexml_load_string( $resposta_api, null, LIBXML_NOERROR );
			
			if ( $xml_resposta == null ) {
				$woocommerce->add_error( 'Não se comunicou com o site da Akatus.' );
			} elseif ( 'erro' == $xml_resposta->status ) {
				$woocommerce->add_error( 'Erro na comunicação com o site da Akatus: ' . $xml_resposta->descricao );
			} else {
				if ( 'Aguardando Pagamento' == $xml_resposta->status ) {
					$order->update_status( 'on-hold', 'Esperando pagamento Akatus,' );
					$is_customer_note = 1;
	                $order->add_order_note( '<p>Forma de pagamento selecionada:  <a href="' . $xml_resposta->url_retorno . '">' . $this->get_descricao() . ', Clique aqui para pagar</a>', $is_customer_note  );

					$order->reduce_order_stock();
					$woocommerce->cart->empty_cart();
					unset( $_SESSION['order_awaiting_payment'] );
					return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg( 'formaPagamentoAkatus', $forma_pagamento, 
									   add_query_arg( 'order', $order->id, 
									   add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'thanks' ) ) ) ) )
					);
				} elseif ( 'Em Análise' == $xml_resposta->status ) {
					$order->update_status( 'on-hold', 'Esperando análise de risco por parte da Akatus'  );
					$is_customer_note = 1;
	                $order->add_order_note( '<p>Forma de pagamento selecionada: ' . $this->get_descricao(), $is_customer_note  );

					$order->reduce_order_stock();
					$woocommerce->cart->empty_cart();
					unset( $_SESSION['order_awaiting_payment'] );
					return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg( 'formaPagamentoAkatus', $forma_pagamento, 
									   add_query_arg( 'order', $order->id, 
									   add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'thanks' ) ) ) ) ) 
					);
				} elseif ( 'Cancelado' == $xml_resposta->status ) {
					$woocommerce->add_error( 'Transação não autorizada, contatar o emissor do cartão.' );
				} else { 
				// Aguardando Pagamento, Em Análise ou Cancelado
					$woocommerce->add_error( 'Status não configurado: ' . $xml_resposta->status );
					$woocommerce->add_error( 'Resposta XML Akatus: ' . htmlentities( print_r( $xml_resposta, true ), ENT_QUOTES, 'UTF-8' ) );
				}
			}
		}			
		
		/**
		 * Descrição da forma de pagamento escolhida
		 **/
		function get_descricao() {
			list( $forma_pagamento,	
				$opcao,
				$parcela ) = explode( '_', $this->get_request( 'formaPagamentoAkatus' ) );

			if ( 'boleto' == $forma_pagamento ) {
				$descricao  = 'Boleto bancário';
			} elseif ( 'cartao' == $forma_pagamento  ) {
				$this->ler_opcoes_akatus();
				$descricao = $this->opcoes_cartoes_akatus[ $forma_pagamento . '_' . $opcao ];
				if ( $parcela  == 1 ) {
					$descricao .= ' à vista';
				} else {
					$descricao .= ' em ' . $parcela . ' parcelas';
				}
			} elseif ( 'tef' == $forma_pagamento ) {
				$this->ler_opcoes_akatus();
				$descricao = $this->opcoes_tefs_akatus[ $forma_pagamento . '_' . $opcao  ];
/*
				if ( $parcela  == 1 ) {
					$descricao .= ' à vista';
				} else {
					$descricao .= ' em ' . $parcela . ' parcelas';
				}
*/
			} else {
				$descricao = $forma_pagamento .  ' não configurada';
			}
			return $descricao;
		}
		

        /**
        * Thank you page
        */
		function thank_you_page () {
			global $woocommerce;
			$order_id = $this->get_request( 'order' );
			$order = new WC_Order( $order_id );
			//check again the status of the order
			if ( $order->status == 'on-hold' || 'completed' == $order->status  ) {
				$notes = $order->get_customer_order_notes();
				if ($notes) :
					
					?>
<h2>
	<?php _e('Order Updates', 'woocommerce'); ?>
</h2>
<ol class="commentlist notes">
	<?php foreach ($notes as $note) : ?>
	<li class="comment note">
		<div class="comment_container">
			<div class="comment-text">
				<p class="meta"><?php echo date_i18n('l jS \of F Y, h:ia', strtotime($note->comment_date)); ?></p>
				<div class="description"> <?php echo wpautop(wptexturize($note->comment_content)); ?> </div>
				<div class="clear"></div>
			</div>
			<div class="clear"></div>
		</div>
	</li>
	<?php endforeach; ?>
</ol>
<?php
				endif;
			} else {
				//display additional failed message
				echo '<p>Para maiores informações ou dúvidas quanto ao seu pedido, <a href="'. add_query_arg('order', $order_id, get_permalink( woocommerce_get_page_id( 'woocommerce_view_order' ) ) ) . '">Clique aqui para ver seu pedido</a> .</p>';
			}
		}
				
		/**
		 * Testa se os dados da Akatus estão preenchidos
		 */
		function testa_dados_akatus() {
			if ( empty( $this->email ) || empty( $this->chave ) ) {
				return false;
			} else {
				return true;
			}
		}
		

		/**
		 * Ler as opções de parcelamento no site da Akatus
		 */
		 public function ler_juros_e_parcelas_isentas_akatus( $method ) {
			require_once 'include/include.php';
			$msg = '?email=' . urlencode( $this->email )  . '&amount=100.00&payment_method=' . $method . '&api_key=' . $this->chave;
			$resposta_api = http_request( PARCELAMENTO . $msg, NULL,  false );
			$xml_resposta = simplexml_load_string( $resposta_api, null, LIBXML_NOERROR );
	
			if ( $xml_resposta == null ) {
				echo '<p>Erro #1! Não se comunicou com o site da Akatus.</p>';
			} elseif ( 'erro' == $xml_resposta->status ) {
				echo '<p>Erro #1 na comunicação com o site da Akatus: ' . $xml_resposta->descricao . '</p>';
			} else {
				$this->juros = $xml_resposta->descricao;
				$this->parcelas_isentas = $xml_resposta->parcelas_assumidas;
				$this->parcelamento_akatus = 'yes';
			}	 
		 }
		 
		/**
		 * Ler as opções de parcelamento no site da Akatus
		 */
		 public function opcoes_parcelamento_akatus( $method, $amount ) {
			require_once 'include/include.php';
			$msg = '?email=' . urlencode( $this->email )  . '&amount=' . $amount . '&payment_method=' . $method . '&api_key=' . $this->chave;
			
//			echo '<pre>' . PARCELAMENTO ; print_r( $msg); echo '</pre>';

			$resposta_api = http_request( PARCELAMENTO . $msg, NULL,  false );
			$xml_resposta = simplexml_load_string( $resposta_api, null, LIBXML_NOERROR );
	
			if ( $xml_resposta == null ) {
				return '<option disabled="disabled">Erro! Não se comunicou com o site da Akatus.</option>';
			} elseif ( 'erro' == $xml_resposta->status ) {
				return '<option disabled="disabled">Erro na comunicação com o site da Akatus: ' . $xml_resposta->descricao . '</option>';
			} else {
				$this->juros = $xml_resposta->descricao;
				$this->parcelas_isentas = $xml_resposta->parcelas_assumidas;
				$this->parcelas = array();
				if ( $this->parcelas_isentas > 0 ) {
					$options = '<option disabled> até ' . $this->parcelas_isentas . ' parcelas sem juros</option>'  ;
				} else {
					$options = '';
				}
				foreach ( $xml_resposta->parcelas as $parcela ) {
					foreach ( $parcela as $opcao ) {
						if ( 1 == $opcao->quantidade ) {
							$options .=  '
								<option value="1">Parcela única de R$ ' . number_format( (float) $opcao->valor, 2, ',', '.' ) . '</option>';
						} else {
							if ( (int) $this->parcelas_isentas >= (int) $opcao->quantidade ) {
								$options .=  '
								<option value="' . $opcao->quantidade . '"> ' . sprintf( '%02d', $opcao->quantidade ) . ' parcelas de R$ ' . number_format( (float) $opcao->valor, 2, ',', '.' ) . '</option>';
							} else {								
								$options .=  '
								<option value="' . $opcao->quantidade . '"> *' .sprintf( '%02d', $opcao->quantidade ) . ' parcelas de R$ ' . number_format( (float)$opcao->valor, 2, ',', '.' ) . ' - Total R$ ' . number_format( (float) $opcao->total, 2, ',', '.' ) . '</option>';
							}								
						}							
					}
				} 
				return $options;
			}
		}


 		/**
		 * Ler as opções de parcelamento no site da Akatus
		 */
		 public function ler_parcelamento_akatus( $method, $amount ) {
			require_once 'include/include.php';
			$msg = '?email=' . urlencode( $this->email )  . '&amount=' . $amount . '&payment_method=' . $method . '&api_key=' . $this->chave;
			
//			echo '<pre>' . PARCELAMENTO ; print_r( $msg); echo '</pre>';

			$resposta_api = http_request( PARCELAMENTO . $msg, NULL,  false );
			$xml_resposta = simplexml_load_string( $resposta_api, null, LIBXML_NOERROR );
	
			if ( $xml_resposta == null ) {
				//echo '<p>Erro! Não se comunicou com o site da Akatus.</p>';
			} elseif ( 'erro' == $xml_resposta->status ) {
				//echo '<p>Erro na comunicação com o site da Akatus: ' . $xml_resposta->descricao . '</p>';
			} else {
				$this->juros = $xml_resposta->descricao;
				$this->parcelas_isentas = $xml_resposta->parcelas_assumidas;
				$this->parcelas = array();
				if ( $this->parcelas_isentas > 0 ) {
					$options = '<option disabled> até ' . $this->parcelas_isentas . ' parcelas sem juros</option>'  ;
				} else {
					$options = '';
				}
				foreach ( $xml_resposta->parcelas as $parcela ) {
					foreach ( $parcela as $opcao ) {
						if ( 1 == $opcao->quantidade ) {
							$options .=  '
								<option value="1">Parcela úncia de R$ ' . number_format( (float) $opcao->valor, 2, ',', '.' ) . '</option>';
						} else {
							if ( (int) $this->parcelas_isentas >= (int) $opcao->quantidade ) {
								$options .=  '
								<option value="' . $opcao->quantidade . '"> ' . $opcao->quantidade . ' parcelas de R$ ' . number_format( (float) $opcao->valor, 2, ',', '.' ) . '</option>';
							} else {								
								$options .=  '
								<option value="' . $opcao->quantidade . '"> *' . $opcao->quantidade . ' parcelas de R$ ' . number_format( (float)$opcao->valor, 2, ',', '.' ) . ' - Total R$ ' . number_format( (float) $opcao->total, 2, ',', '.' ) . '</option>';
							}								
						}							
					}
				} 
				return $options;
			}
		}

		/**
		 * Ler as opções de parcelamento no site da Akatus
		 */
		 public function ler_parcelamento_akatusx( $method, $amount = 1000.00 ) {
			require_once 'include/include.php';
			$msg = '?email=' . urlencode( $this->email )  . '&amount=' . $amount . '&payment_method=' . $method . '&api_key=' . $this->chave;
			
//			echo '<pre>' . PARCELAMENTO ; print_r( $msg); echo '</pre>';

			$resposta_api = http_request( PARCELAMENTO . $msg, NULL,  false );
			$xml_resposta = simplexml_load_string( $resposta_api, null, LIBXML_NOERROR );
	
			if ( $xml_resposta == null ) {
				echo '<p>Erro! Não se comunicou com o site da Akatus.</p>';
			} elseif ( 'erro' == $xml_resposta->status ) {
				echo '<p>Erro na comunicação com o site da Akatus: ' . $xml_resposta->descricao . '</p>';
			} else {
				$this->juros = $xml_resposta->descricao;
				$this->parcelas_isentas = $xml_resposta->parcelas_assumidas;
				$this->parcelas = array();
				$retorno =  'Juros de ' . $xml_resposta->descricao;
				if ( $xml_resposta->parcelas_assumidas > 0 ) {
					$retorno .= ',  parcelas assumidas: ' . $xml_resposta->parcelas_assumidas;
				}
/*
				foreach ( $xml_resposta->parcelas as $parcela ) {
					foreach ( $parcela as $opcao ) {
						
						$retorno .=  ' <br />' . $opcao->quantidade . ' parcela(s) de R$ ' . number_format( $opcao->valor, 2, ',', '.' ) . ' - Total R$ ' . number_format( $opcao->total, 2, ',', '.' );
					}
				} 
*/
				return $retorno;
			}
		}
		/**
		 * Ler as opções do site da Akatus
		 */
		 function ler_opcoes_akatus() {
			require_once 'include/include.php';
			$msg = $this-> XML_header() 
				 . $this->XML_opcoes_de_pagamento();
			$resposta_api = http_request( ENDERECO, $msg );
			$xml_resposta = simplexml_load_string( $resposta_api, null, LIBXML_NOERROR );

			if ( $xml_resposta == null ) {
				echo '<p>Erro! Não se comunicou com o site da Akatus.</p>';
			} elseif ( 'erro' == $xml_resposta->status ) {
				echo '<p>Erro na comunicação com o site da Akatus: ' . $xml_resposta->descricao . '</p>';
			} else {
				$this->boleto_akatus            = 'no';
				$this->parcelamento_akatus      = 'no';
				$this->opcoes_cartoes_akatus    = array();
				$this->parcelas_cartoes_akatus  = array();
				$this->opcoes_tefs_akatus       = array();
				$this->parcelas_tefs_akatus     = array();
				
				foreach ( $xml_resposta->meios_de_pagamento as $meio_de_pagamento ) {
					foreach ( $meio_de_pagamento as $opcao ) {
						if ( 'Boleto Bancário' == $opcao->descricao ) {
							$this->boleto_akatus = 'yes';
						} elseif ( 'Cartão de Crédito' == $opcao->descricao ) {
							foreach ( $opcao->bandeiras as $bandeira ) {
								foreach( $bandeira as $cartao ) {
									$this->opcoes_cartoes_akatus[ (string)$cartao->codigo ]   = (string) $cartao->descricao;
									$this->parcelas_cartoes_akatus[ (string)$cartao->codigo ] = (string) $cartao->parcelas;
									if ( 'no' == $this->parcelamento_akatus ) {
										$this->ler_juros_e_parcelas_isentas_akatus( (string)$cartao->codigo );
									}
								}
							}
						} elseif ( 'TEF' == $opcao->descricao ) {
							foreach ( $opcao->bandeiras as $bandeira ) {
								foreach ( $bandeira as $tef ) {
									$this->opcoes_tefs_akatus[ (string) $tef->codigo ]   = (string) $tef->descricao;
									$this->parcelas_tefs_akatus[ (string) $tef->codigo ] = (string) $tef->parcelas;
								}
							}
						}
					}
				}
				return true;
			}
			return false;
		}
			
		/**
		 * Mostar as opções do site da Akatus
		 */
		function mostrar_opcoes_akatus() {
?>
<h4>Opções configuradas no site da Akatus</h4>
<?php
			if ( $this->ler_opcoes_akatus() ) {
?>
<table class="form-table">
	<tbody>
		<tr valign="top">
			<th scope="row" class="titledesc">Boleto bancário</th>
			<td class="forminp">
				<fieldset>
				<legend class="screen-reader-text"><span>Boleto bancário</span></legend>
				<label for="woocommerce_akatus_boleto">
				<?php
				if ( 'yes' == $this->boleto_akatus ) {
?>
				<input name="woocommerce_akatus_boleto" id="woocommerce_akatus_boleto" value="1" type="checkbox" checked="checked" readonly="readonly"  />
				<?php 
				} else {
?>
				<input name="woocommerce_akatus_boleto" id="woocommerce_akatus_boleto" value="1" type="checkbox" readonly="readonly"  />
				<?php 
				}
?>
				Aceitar pagamento com boleto bancário<br />
				<span class="description"><strong>Esta seleção deve ser feita no site da Akatus</strong></span></label>
				<br>
				</fieldset>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="woocommerce_akatus_tefs">Transferências</label>
			</th>
			<td class="forminp">
				<fieldset>
				<legend class="screen-reader-text"><span>Transferências</span></legend>
				<select multiple="multiple" class="multiselect" name="woocommerce_akatus_tefs[]" id="woocommerce_akatus_tefs"  readonly="readonly" >
					<?php
					foreach ( $this->opcoes_tefs_akatus as $option_key => $option_value ) :
						echo '<option value="'.$option_key.'" selected="selected">'.$option_value.'</option>';
					endforeach;
?>
				</select>
				<br />
				<span class="description"><strong>Esta seleção deve ser feita no site da Akatus</strong></span>
				</fieldset>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="woocommerce_akatus_cartoes">Bandeiras aceitas</label>
			</th>
			<td class="forminp">
				<fieldset>
				<legend class="screen-reader-text"><span>Bandeiras aceitas</span></legend>
				<select multiple="multiple" class="multiselect" name="woocommerce_akatus_cartoes[]" id="woocommerce_akatus_cartoes"  readonly="readonly" >
					<?php
					foreach ( $this->opcoes_cartoes_akatus as $option_key => $option_value ) :
						echo '<option value="'.$option_key.'" selected="selected">'.$option_value.'</option>';
					endforeach;
?>
				</select>
				<br />
				<span class="description"><strong>Esta seleção deve ser feita no site da Akatus</strong></span>
				</fieldset>
			</td>
		</tr>
<?php
				if ( 'yes' == $this->parcelamento_akatus ) {
?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="wp_e_commerce_akauts_cartoes">Juros do parcelamento</label>
			</th>
			<td class="forminp">
				<fieldset>
				<legend class="screen-reader-text"><span><?php echo $this->juros ?></span></legend>
				Juros de  <input type="text" disabled="disabled" readonly="readonly" value="<?php echo $this->juros ?>" /><br />
				 <span class="description"><strong>Esta seleção vem pronta do site da Akatus</strong></span>
				</fieldset>
			</td>
		</tr>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="wp_e_commerce_akauts_cartoes">Parcelamento</label>
				</th>
				<td class="forminp">
					<fieldset>
					<legend class="screen-reader-text"><span>Parcelamento pelas bandeiras</span></legend>
					<select multiple="multiple" class="multiselect" name="wp_e_commerce_akauts_parcelamento[]" id="wp_e_commerce_akauts_parcelamento"  readonly="readonly" >
						<?php
						foreach ( $this->parcelas_cartoes_akatus as $option_key => $option_value ) :
							echo '<option value="'.$option_key.'" selected="selected">'.  $this->opcoes_cartoes_akatus[$option_key] . ' em até ' .  $option_value . ' parcelas </option>';
						endforeach;
	?>
					</select>
					<br />
					<span class="description"><strong>Esta seleção vem pronta do site da Akatus</strong></span>
					</fieldset>
				</td>
			</tr>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="wp_e_commerce_akauts_cartoes">Assumir custo dos juros</label>
			</th>
			<td class="forminp">
				<fieldset>
				<legend class="screen-reader-text"><span>Parcelamento</span></legend>
<?php
						if ( 0 == $this->parcelas_isentas ) {
?>				
						Não desejo assumir os juros mensais dos meus clientes para vendas por cartão de crédito.<br />
<?php 
						} else {
?>											
				<input type="checkbox"  checked="checked" disabled="disabled" readonly="readonly" />
				Sim, desejo assumir os juros mensais dos meus clientes de até
				<select id="assumed_installments" name="assumed_installments" readonly="readonly"  >
					<option><?php echo $this->parcelas_isentas; ?> parcelas</option>
				</select>
				para vendas por cartão de crédito.<br />
<?php
						}
?>				
 <span class="description"><strong>Esta seleção deve ser feita no site da Akatus</strong></span>
				</fieldset>
			</td>
		</tr>
		<?php
					}
				}
?>
	</tbody>
</table>
<?php		}
		
		private function XML_header() {
			return '<?xml version="1.0" encoding="UTF-8" ?>'; 
		}

		/**
		 * Gera XML para pesquisar opcoes de pagamento habilitadas na Akatus
		 */
		private function XML_opcoes_de_pagamento() {
			$msg = '
<meios_de_pagamento>
    <correntista>
       <api_key>' . $this->chave . '</api_key>
       <email>' . $this->email . '</email>
    </correntista>
</meios_de_pagamento>';
			return $msg;
		}

		/**
		 * Gera XML para pagamento na Akatus
		 */
		private function XML_pagamento( $order ) {
			$telefone = preg_replace('/[^0-9]+/', '', $this->get_request( 'billing_phone' ) );
//            $descricao = htmlentities( '<a href="' . esc_url( add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('view_order'))) ). '">Pedido #' . $order->id .  '</a>', ENT_QUOTES, 'UTF-8' );
            $descricao = htmlentities( 'Pedido #' . $order->id .  ' ' . esc_url( add_query_arg('order', $order->id, get_permalink(woocommerce_get_page_id('view_order'))) ), ENT_QUOTES, 'UTF-8' );
			
			$msg = '
<carrinho>
    <recebedor>
       <api_key>' . $this->chave . '</api_key>
       <email>' . $this->email . '</email>
    </recebedor>
    <pagador>
        <nome>' . $this->get_request( 'billing_first_name' ) . ' ' . $this->get_request( 'billing_last_name' ) .  ' </nome>
        <email>' . $this->get_request( 'billing_email' ) .  '</email>
        <telefones>
            <telefone>
                <tipo>residencial</tipo>
                <numero>' . $telefone .  '</numero>
            </telefone>
        </telefones>
    </pagador>
    <produtos>
        <produto>
            <codigo>' . $order->id .  '</codigo>
            <descricao>' . $descricao . '</descricao>
            <quantidade>1</quantidade>
            <preco>' . $order->order_total .  '</preco>
            <peso>0</peso>
            <frete>0</frete>
            <desconto>0</desconto>
        </produto>
    </produtos>';
	
			if ( 'boleto' == $this->get_request( 'formaPagamentoAkatus' ) ) {
				$msg .= '
    <transacao>
        <desconto_total>0</desconto_total>
        <peso_total>0</peso_total>
        <frete_total>0</frete_total>
        <moeda>BRL</moeda>
        <referencia>' . $order->id .  '</referencia>
        <meio_de_pagamento>boleto</meio_de_pagamento>
    </transacao>';
			} elseif ( strstr( $this->get_request( 'formaPagamentoAkatus' ), 'tef' ) ) {
				$msg .= '
    <transacao>
        <desconto_total>0</desconto_total>
        <peso_total>0</peso_total>
        <frete_total>0</frete_total>
        <moeda>BRL</moeda>
        <referencia>' . $order->id .  '</referencia>
        <meio_de_pagamento>' . $this->get_request( 'formaPagamentoAkatus' ) . '</meio_de_pagamento>
    </transacao>';
			} elseif ( strstr( $this->get_request( 'formaPagamentoAkatus' ), 'cartao' ) ) {
				$cartao = preg_replace('/[^0-9]+/', '', $this->get_request('card_number') );							
				$cpf    = preg_replace('/[^0-9]+/', '', $this->get_request('cpf') );
				$msg .= '
	<transacao>
        <numero>' . $cartao .  '</numero>
        <parcelas>' . $this->get_request( 'parcelas' ) .  '</parcelas>
        <codigo_de_seguranca>' . $this->get_request( 'cvv' ) .  '</codigo_de_seguranca>
        <expiracao>' . $this->get_request( 'expiry_month' ) .  '/' .  $this->get_request( 'expiry_year' ) . '</expiracao>
        <desconto_total>0</desconto_total>
        <peso_total>0</peso_total>
        <frete_total>0</frete_total>
        <moeda>BRL</moeda>
        <referencia>' . $order->id .  '</referencia>
        <meio_de_pagamento>' . $this->get_request( 'formaPagamentoAkatus' ) . '</meio_de_pagamento>
        <portador>
                  <nome>' . $this->get_request( 'name_on_card' ) .  '</nome>
                  <cpf>' . $cpf .  '</cpf>
                 <telefone>' . $telefone .  '</telefone>
        </portador>
	</transacao>';
	}
	$msg .='
</carrinho>
';
			return $msg;
		}
		/**
		 * Get $_REQUEST data if set
		 **/
		private function get_request( $name ) {
			if ( isset( $_REQUEST[ $name ] ) ) {
				return $_REQUEST[ $name ];
			} else {
				return NULL;
			}
		}
	}

	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_akatus_gateway( $methods ) {
		$methods[] = 'WC_akatus_gateway';
		return $methods;
	}

	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_akatus_gateway' );
} 
?>