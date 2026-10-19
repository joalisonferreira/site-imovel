<?php
/**
 * PT-BR nas telas do CRM Houzez (importação de leads e mensagens do fluxo).
 *
 * Página: /atividades/?hpage=import-leads (template/user_dashboard_crm.php).
 * O template do tema (template-parts/dashboard/board/leads/import.php, domínio
 * 'houzez') e o plugin houzez-crm (domínio 'houzez-crm', sem .mo pt_BR — só
 * .pot) exibem textos em inglês. Em vez de editar arquivos de terceiros
 * (perdidos a cada update), traduzimos via filtro 'gettext', no mesmo padrão
 * de Imovel_Parceiro_Pix_Subscriptions::translate_pix_strings().
 *
 * Limite conhecido: os rótulos da etapa de mapeamento de colunas são montados
 * no JS (houzez-crm/js/script.js) a partir das chaves técnicas (first_name,
 * last_name...) e não passam por __(); seguem em inglês.
 *
 * @see class-pix-subscriptions.php:207 (precedente do padrão gettext).
 */
class Imovel_Parceiro_CRM_PTBR {

    /**
     * Strings do TEMA (domínio 'houzez') visíveis na tela de importação.
     * Chave = original exato; valor = PT-BR.
     */
    const HOUZEZ_STRINGS = array(
        'Import' => 'Importar',
        'This page allows you to easily import CSV files into our system. Follow these simple steps to seamlessly transfer your data:' => 'Esta página permite importar arquivos CSV para o sistema de forma fácil. Siga estes passos simples para transferir seus dados:',
        '<strong>Prepare your CSV file:</strong> Ensure your file is correctly formatted with clear column headers and accurate data.' => '<strong>Prepare seu arquivo CSV:</strong> confira se o arquivo está formatado corretamente, com cabeçalhos claros e dados precisos.',
        '<strong>Upload your CSV file:</strong> Click the "Choose File" button to select your CSV file from your device.' => '<strong>Envie seu arquivo CSV:</strong> clique no botão "Escolher arquivo" para selecionar o CSV no seu dispositivo.',
        'Fetch Data' => 'Buscar dados',
        'Select' => 'Selecionar',
        'No files uploaded.' => 'Nenhum arquivo enviado.',
        'Previous Imported Files' => 'Arquivos enviados anteriormente',
        'File Name' => 'Nome do arquivo',
        'Import Date' => 'Data de envio',
        'Actions' => 'Ações',
        'at' => 'às',
        'Delete' => 'Excluir',
        'Upload' => 'Enviar',
    );

    /**
     * Strings do PLUGIN houzez-crm (domínio 'houzez-crm'): variáveis do JS
     * (Houzez_crm_vars), mensagens de upload e rótulos de exportação.
     */
    const CRM_STRINGS = array(
        'Processing, Please wait...' => 'Processando, aguarde...',
        'Are you sure you want to do this?' => 'Tem certeza de que deseja fazer isso?',
        'Are you sure you want to delete?' => 'Tem certeza de que deseja excluir?',
        'Are you sure you want to send email?' => 'Tem certeza de que deseja enviar o e-mail?',
        'Delete' => 'Excluir',
        'Cancel' => 'Cancelar',
        'Confirm' => 'Confirmar',
        'Select' => 'Selecionar',
        'Import' => 'Importar',
        'Please map at least one field.' => 'Mapeie pelo menos um campo.',
        'Error in Importing Data.' => 'Erro ao importar os dados.',
        'File is uploaded successfully. Redirecting...' => 'Arquivo enviado com sucesso. Redirecionando...',
        "You don't have permission to upload file" => 'Você não tem permissão para enviar arquivos.',
        'You do not have permission to upload files.' => 'Você não tem permissão para enviar arquivos.',
        'Nonce verification failed.' => 'Falha na verificação de segurança.',
        'File not found.' => 'Arquivo não encontrado.',
        'Data imported successfully.' => 'Dados importados com sucesso.',
        'Please select title!' => 'Selecione o tratamento!',
        'Please enter your full name!' => 'Informe seu nome completo!',
        'Invalid email address.' => 'Endereço de e-mail inválido.',
        'Lead Successfully updated!' => 'Lead atualizado com sucesso!',
        'Lead Successfully added!' => 'Lead adicionado com sucesso!',
        'Email already exist, try different email address' => 'Este e-mail já existe. Tente outro endereço.',
        'Security check failed!' => 'Falha na verificação de segurança!',
        'You do not have permission to access this resource.' => 'Você não tem permissão para acessar este recurso.',
        'Something went wrong!' => 'Algo deu errado!',
        'Lead not found or you do not have permission to access it.' => 'Lead não encontrado ou você não tem permissão para acessá-lo.',
        'No lead id found' => 'Nenhum lead encontrado.',
        "You don't have rights to perform this action" => 'Você não tem permissão para realizar esta ação.',
        'No Item Selected' => 'Nenhum item selecionado.',
        'Prefix' => 'Prefixo',
        'First Name' => 'Nome',
        'Last Name' => 'Sobrenome',
        'Full Name' => 'Nome completo',
        'Email' => 'E-mail',
        'Mobile' => 'Celular',
        'Home Phone' => 'Telefone residencial',
        'Work Phone' => 'Telefone comercial',
        'Address' => 'Endereço',
        'City' => 'Cidade',
        'County / State' => 'Estado',
        'Country' => 'País',
        'Postal Code / Zip' => 'CEP',
        'Type' => 'Tipo',
        'Source' => 'Origem',
        'Source Link' => 'Link de origem',
        'Twitter' => 'Twitter',
        'Linkedin' => 'LinkedIn',
        'Facebook' => 'Facebook',
        'Private Note' => 'Nota privada',
        'Message' => 'Mensagem',
    );

    public function __construct() {
        add_filter( 'gettext', array( $this, 'translate_strings' ), 20, 3 );
    }

    /**
     * Traduz strings dos domínios 'houzez' (tela de importação) e
     * 'houzez-crm' (fluxo de leads). Só no front; admin intacto.
     *
     * @param string $translation Tradução atual.
     * @param string $text Texto original.
     * @param string $domain Domínio.
     * @return string
     */
    public function translate_strings( $translation, $text, $domain ) {
        if ( is_admin() ) {
            return $translation;
        }
        if ( 'houzez' === $domain && isset( self::HOUZEZ_STRINGS[ $text ] ) ) {
            return self::HOUZEZ_STRINGS[ $text ];
        }
        if ( 'houzez-crm' === $domain && isset( self::CRM_STRINGS[ $text ] ) ) {
            return self::CRM_STRINGS[ $text ];
        }
        return $translation;
    }
}

new Imovel_Parceiro_CRM_PTBR();
