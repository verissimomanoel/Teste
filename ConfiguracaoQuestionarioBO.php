<?php

/**
 * Classe responsável pelas regras referentes as configuração de questionarios;
 *
 * @author Squadra Tecnologia.
 * @date 13/10/2017
 */
class ConfiguracaoQuestionarioBO extends GenericoBO
{
    const ASSUNTO_EMAIL_INFORMATIVO = "Informativo - SISTEC";

    const TP_PUBLICO_ALVO_ALUNO = 1;

    const TP_PUBLICO_ALVO_MANTENEDORA = 3;

    const TP_PUBLICO_ALVO_UNIDADE_ENSINO = 2;

    const TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA = 1;

    const TP_SITUACAO_DISPONIVEL_PROGRAMADO = 2;

    const TP_SITUACAO_DISPONIVEL_FINALIZADO = 3;

    const ST_CONFIGURACAO_QUESTIONARIO_ATIVA = true;

    const FRENTE_QUESTIONARIO_CURSO = 1;

    const FRENTE_QUESTIONARIO_TURMA = 2;

    const FRENTE_QUESTIONARIO_UNIDADE_ENSINO = 3;

    const FRENTE_QUESTIONARIO_EIXO_TECNOLOGICO = 4;

    const TP_INICIATIVA_SISUTEC = 1;

    const TP_INICIATIVA_MEDIOTEC = 2;

    const TP_INICIATIVA_PRONATEC_VOLUNTARIO = 3;

    const TP_INICIATIVA_PACTUACAO = 4;

    const COR_VERDE = 'VERDE';

    const COR_VERMELHO = 'VERMELHO';

    const COR_AZUL = 'AZUL';

    const COR_CINZA = 'CINZA';

    private $msgInativoRedisponibilidade = "Configuração do questionário inativada com sucesso.";

    private $msgAtivoRedisponibilidade = "Questionário ativado com sucesso.";

    private $msgStatusInativoConfiguracao = [
        self::TP_SITUACAO_DISPONIVEL_FINALIZADO => "Configuração do questionário inativada com sucesso.",
        self::TP_SITUACAO_DISPONIVEL_PROGRAMADO => "Configuração do questionário inativada, a visualização desse questionário não será apresentada para ",
        self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA => "Configuração do questionário inativada, foi removida a visualização desse questionário do "
    ];

    private $msgStatusAtivoConfiguracao = [
        self::TP_SITUACAO_DISPONIVEL_FINALIZADO => "Questionário ativado com sucesso.",
        self::TP_SITUACAO_DISPONIVEL_PROGRAMADO => "Questionário reativado com sucesso! Esse questionário será disponibilizado para o aluno/unidade de ensino na data programada."
    ];

    private $nomePublicoAlvo = [
        1 => 'Aluno',
        2 => 'Unidade de Ensino',
        3 => 'Mantenedora'
    ];

    private $nomeFrenteQuestionario = [
        1 => 'Curso',
        2 => 'Turma',
        3 => 'Unidade de Ensino',
        4 => 'Eixo Tecnológico'
    ];

    /**
     * Retorna uma nova instância de Configuracao de Questionário.
     *
     * @return ConfiguracaoQuestionarioBO
     */
    public static function newInstance()
    {
        return new ConfiguracaoQuestionarioBO();
    }

    /**
     * Recupa a configuracao de questionário a partir do id informado.
     *
     * @param $coConfiguracaoQuestionario
     * @return array
     * @throws Erro
     */
    public function getConfiguracaoQuestionario($coConfiguracaoQuestionario)
    {
        if (empty($coConfiguracaoQuestionario)) {
            throw new Erro('Parâmentros informados inválidos.');
        }
        return $this->getConfiguracaoQuestionarioDAO()->getConfiguracaoQuestionario($coConfiguracaoQuestionario);
    }

    /**
     * Recupa as redisponibilizações para a configuração de questionário informada.
     *
     * @param $coConfiguracaoPai
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public function getRedisponibilizacoesPorConfiguracao($coConfiguracaoPai)
    {
        return $this->getConfiguracaoQuestionarioDAO()->getRedisponibilizacoesPorConfiguracao($coConfiguracaoPai);
    }

    /**
     * Valida qual situação deve ser considerada e retorna dados da configuração do questionario.
     *
     * @param $coConfiguracao
     * @return stdClass
     * @throws Aviso
     */
    public function validarAlterarStatus($coConfiguracao)
    {
        $configuracaoQuestionario = $this->getConfiguracaoQuestionario($coConfiguracao);
        $situacoes = [
            $configuracaoQuestionario->tp_situacao
        ];
        $redisponibilidades = $this->getRedisponibilizacoesPorConfiguracao($coConfiguracao);
        $situacoes = $this->getSituacaoRedisponibilidade($coConfiguracao, $redisponibilidades, $situacoes);

        $configuracaoTO = new stdClass();
        $configuracaoTO->co_configuracao_questionario = $coConfiguracao;
        $configuracaoTO->situacao_prioridade = reset($situacoes);
        $configuracaoTO->isAtivo = $configuracaoQuestionario->st_ativo;
        $configuracaoTO->isRedisponibilidade = (count($redisponibilidades) > 0) ? true : false;

        return $configuracaoTO;
    }

    /**
     * Altera o 'status' da configuração do questionário conforme o código informado.
     *
     * @param $coConfiguracao
     * @return stdClass
     * @throws Aviso
     */
    public function alterarStatus($coConfiguracao)
    {
        $statusTO = new stdClass();
        $statusTO->coConfiguracaoQuestionario = $coConfiguracao;

        $configuracao = $this->getConfiguracaoQuestionarioDAO()->getConfiguracaoQuestionario($coConfiguracao);
        $redisponibilidades = $this->getRedisponibilizacoesPorConfiguracao($coConfiguracao);
        $noPublicoAlvo = $this->nomePublicoAlvo[$configuracao->tp_publico_alvo];

        if (count($redisponibilidades) > 0) {
            $this->ativacaoInativacaoRedisponibilidade($configuracao, $statusTO, $redisponibilidades);
        } else {
            $this->ativacaoInativacaoConfiguracao($configuracao, $statusTO, $noPublicoAlvo);
        }

        return $statusTO;
    }

    /**
     * Salva a configuração do questionário no sistema.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @throws Aviso
     * @throws Erro
     */
    public function salvar(stdClass $configuracaoQuestionarioTO)
    {
        $dataAtual = new Zend_Date();
        $this->validarCamposObrigatorios($configuracaoQuestionarioTO);
        $this->validarDatas($configuracaoQuestionarioTO);

        if ($configuracaoQuestionarioTO->st_informativo_vinculado) {
            $this->validarDataInformativoQuestionario($configuracaoQuestionarioTO);
        }

        if ($configuracaoQuestionarioTO->st_envia_email_informativo) {
            $this->validarDataEnvioEmailInformativo($configuracaoQuestionarioTO);
            $this->validarPeriodoEnvioEmailInformativo($configuracaoQuestionarioTO);
        }

        if ($configuracaoQuestionarioTO->st_informativo_vinculado && !$configuracaoQuestionarioTO->st_informativo_periodo_questionario) {
            if ($configuracaoQuestionarioTO->co_frente == static::FRENTE_QUESTIONARIO_TURMA) {
                $turmas = $configuracaoQuestionarioTO->dados_frente;
            }

            if ($configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_MEDIOTEC || $configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_SISUTEC) {
                $this->validarPeriodoInformativo($configuracaoQuestionarioTO->dt_inicio_informativo, $configuracaoQuestionarioTO->dt_termino_informativo, $configuracaoQuestionarioTO->co_tipo_iniciativa, $configuracaoQuestionarioTO->co_adesao_sisu_tecnico_edital, $turmas);
            }

            if ($configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_PRONATEC_VOLUNTARIO) {
                $this->validarPeriodoInformativo($configuracaoQuestionarioTO->dt_inicio_informativo, $configuracaoQuestionarioTO->dt_termino_informativo, $configuracaoQuestionarioTO->co_tipo_iniciativa, $configuracaoQuestionarioTO->co_adesao_edital_lote, $turmas);
            }

            if ($configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_PACTUACAO) {
                $this->validarPeriodoInformativo($configuracaoQuestionarioTO->dt_inicio_informativo, $configuracaoQuestionarioTO->dt_termino_informativo, $configuracaoQuestionarioTO->co_tipo_iniciativa, $configuracaoQuestionarioTO->co_pactuacao_periodo, $turmas);
            }
        }

        if (!empty($configuracaoQuestionarioTO->co_configuracao_questionario)) {
            $coFrente = $this->getConfiguracaoQuestionarioDAO()->getCoFrente($configuracaoQuestionarioTO->co_configuracao_questionario);
            $nuMesAnoRemovidos = $this->getNuMesAnoRemovidos($configuracaoQuestionarioTO, $configuracaoQuestionarioTO->co_configuracao_questionario);
            $regioesRemovidas = $this->getRegioesRemovidas($configuracaoQuestionarioTO, $configuracaoQuestionarioTO->co_configuracao_questionario);
            $ufsRemovidas = $this->getUfsRemovidas($configuracaoQuestionarioTO, $configuracaoQuestionarioTO->co_configuracao_questionario);
            $municipiosRemovidos = $this->getMunicipiosRemovidos($configuracaoQuestionarioTO, $configuracaoQuestionarioTO->co_configuracao_questionario);
        }

        try {
            $this->getConfiguracaoQuestionarioDAO()->beginTransaction();

            $configuracaoQuestionario = $this->getNovaConfiguracaoQuestionario($configuracaoQuestionarioTO);
            $configuracaoQuestionario = $this->getConfiguracaoQuestionarioDAO()->salvarConfiguracaoQuestionario($configuracaoQuestionario);
            $coConfiguracaoQuestionario = $configuracaoQuestionario->co_configuracao_questionario;

            if (empty($configuracaoQuestionarioTO->co_configuracao_questionario)) {
                $this->salvarHistorico($coConfiguracaoQuestionario,
                    ConfiguracaoQuestionarioHistoricoBO::ACAO_CADASTRAR);
            } else {
                $this->salvarHistorico($coConfiguracaoQuestionario, ConfiguracaoQuestionarioHistoricoBO::ACAO_ALTERAR,
                    $configuracaoQuestionarioTO->ds_justificativa_alteracao);
            }

            $this->salvarDisponibilidades($configuracaoQuestionarioTO->disponibilidades, $coConfiguracaoQuestionario);
            $this->salvarUfs($configuracaoQuestionarioTO->ufs, $coConfiguracaoQuestionario);

            if ($configuracaoQuestionarioTO->st_macrorregiao && !empty($configuracaoQuestionarioTO->macrorregioes)) {
                $this->salvarRegioes($configuracaoQuestionarioTO->macrorregioes, $coConfiguracaoQuestionario);
            }

            if (!empty($configuracaoQuestionarioTO->municipios)) {
                $this->salvarMunicipio($configuracaoQuestionarioTO->municipios, $coConfiguracaoQuestionario);
            }

            $this->salvarFrente($configuracaoQuestionarioTO, $coConfiguracaoQuestionario);

            if (!empty($configuracaoQuestionarioTO->co_configuracao_questionario)) {
                $this->excluirDisponibilidades($nuMesAnoRemovidos, $coConfiguracaoQuestionario);
                $this->excluirRegioes($regioesRemovidas, $coConfiguracaoQuestionario);
                $this->excluirUfs($regioesRemovidas, $ufsRemovidas, $coConfiguracaoQuestionario);
                $this->excluirMunicipios($ufsRemovidas, $municipiosRemovidos, $coConfiguracaoQuestionario);
                $this->excluirFrente($configuracaoQuestionarioTO, $coConfiguracaoQuestionario, $coFrente);

            }

            $this->atualizarStatusDisponibilidade($configuracaoQuestionario);

            if ($configuracaoQuestionario->co_tipo_iniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO) {
                $configuracaoPactuacao = $this->salvarConfiguracaoQuestionarioPactuacao($configuracaoQuestionarioTO, $configuracaoQuestionario->co_configuracao_questionario);
                $this->salvarEsferasAdministrativas($configuracaoQuestionarioTO->esferas_administrativas, $configuracaoPactuacao->co_configuracao_questionario_pactuacao);
                $this->salvarSubEsferasAdministrativas($configuracaoQuestionarioTO->sub_esfera_administrativas, $configuracaoPactuacao->co_configuracao_questionario_pactuacao);

                if (!empty($configuracaoQuestionarioTO->co_configuracao_pactuacao)) {
                    $this->excluirEsferaAdministrativa($configuracaoQuestionarioTO, $configuracaoQuestionarioTO->co_configuracao_pactuacao);
                    $this->excluirSubEsferaAdmistrativa($configuracaoQuestionarioTO, $configuracaoQuestionarioTO->co_configuracao_pactuacao);
                }
            }

            $this->getConfiguracaoQuestionarioDAO()->commit();
        } catch (Exception $e) {
            $this->getConfiguracaoQuestionarioDAO()->rollback();
            throw new Erro($e->getMessage());
        }
    }

    /**
     * Salva a redisponibilização da configuração no sistema.
     *
     * @param stdClass $redisponibilidadeTO
     * @throws Aviso
     * @throws Erro
     * @throws Zend_Date_Exception
     */
    public function salvarRedisponibilizacao(stdClass $redisponibilidadeTO)
    {
        $this->validarCamposObrigatoriosRedisponibilizacao($redisponibilidadeTO);

        if (boolval($redisponibilidadeTO->stInformativoVinculado)) {
            $this->validarDataInformativoRedisponibilidade($redisponibilidadeTO);
            $this->validarDatasPeriodoRedisponibilidade($redisponibilidadeTO);
        }

        if (boolval($redisponibilidadeTO->stEnviaEmailInformativo)) {
            $this->validarDataEnvioEmailInformativoRedisponibilidade($redisponibilidadeTO);
        }

        try {
            $this->getConfiguracaoQuestionarioDAO()->beginTransaction();
            $redisponibilidade = $this->getConfiguracaoQuestionarioDAO()->salvarConfiguracaoQuestionario($this->getNovaRedisponibilidade($redisponibilidadeTO));
            $this->salvarDisponibilidades($redisponibilidadeTO->redisponibilidades, $redisponibilidade->co_configuracao_questionario);
            $this->salvarAbrangenciaRedisponibilidade($redisponibilidadeTO, $redisponibilidade);

            if ($redisponibilidade->co_tipo_iniciativa == static::TP_INICIATIVA_PACTUACAO && empty($redisponibilidadeTO->coRedisponibilizacao)) {
                $redisponibilizacaoPactuacao = $this->salvarRedisponibilizacaoPactuacao($redisponibilidade);
                $this->salvarAbrangenciaPactuacao($redisponibilizacaoPactuacao, $redisponibilidade);
            }

            $this->atualizarStatusDisponibilidade($redisponibilidade);

            if (!empty($redisponibilidadeTO->coRedisponibilizacao)) {
                $disponibilidadesRemovidas = $this->getMesesAnoRemovidosRedisponibilidade($redisponibilidadeTO->redisponibilidades, $redisponibilidade->co_configuracao_questionario);
                $this->excluirDisponibilidades($disponibilidadesRemovidas, $redisponibilidade->co_configuracao_questionario);
            }

            if (empty($redisponibilidadeTO->coRedisponibilizacao)) {
                $nuRedisponibilizacoes = $this->getConfiguracaoQuestionarioDAO()->getCountRedisponibilizacoes($redisponibilidade->co_conf_questionario_pai);
                $dsAcao = $nuRedisponibilizacoes . ' Redisponibilização de Questionário';
            } else {
                $countRedisponibilidades = $this->getCountRedisponibilizacoes($redisponibilidade->co_conf_questionario_pai);
                $ultimaRedisponibilidadeCadastrada = $this->getUltimaReconfiguracaoPorConfiguracao($redisponibilidade->co_conf_questionario_pai);
                $isUltimaRedisponibilidade = $ultimaRedisponibilidadeCadastrada == $redisponibilidade->co_configuracao_questionario;
                $nuRedisponibilidade = ($isUltimaRedisponibilidade && $countRedisponibilidades == 2) ? 2 : 1;
                $dsAcao = 'Alteração da ' . $nuRedisponibilidade . ' Redisponibilização de Questionário';
            }

            $this->salvarHistorico($redisponibilidade->co_conf_questionario_pai, $dsAcao);

            $this->getConfiguracaoQuestionarioDAO()->commit();
        } catch (Exception $e) {
            $this->getConfiguracaoQuestionarioDAO()->rollback();
            throw new Erro($e->getMessage());
        }
    }

    /**
     * Regras de negocio para Excluir uma configuração do questionario .
     *
     * @param integer $coConfiguracaoQuestionario
     * @param boolean $confirmacaoExclusao
     * @param string $baseUrl
     */
    public function excluir($coConfiguracao, $confirmacaoExclusao, $baseUrl)
    {
        $this->validarSituacaoConfiguracaoQuestionario($coConfiguracao, $confirmacaoExclusao, $baseUrl);
        $this->getConfiguracaoQuestionarioDAO()->excluir($coConfiguracao);
    }

    /**
     * Regras de negocio para Excluir uma redisponibilidade configuração do questionario .
     *
     * @param integer $coConfiguracaoQuestionario
     * @param string $baseUrl
     */
    public function excluirRedisponibilidade($redisponibilidade)
    {
        $this->validarSituacaoRedisponibilidade($redisponibilidade->co_configuracao_questionario);
        $this->getConfiguracaoQuestionarioDAO()->excluir($redisponibilidade->co_configuracao_questionario);

        $countRedisponibilidade = $this->getConfiguracaoQuestionarioDAO()->getCountRedisponibilizacoes($redisponibilidade->co_conf_questionario_pai) + 1;
        $dsAcao = "Exclusão da {$countRedisponibilidade} Redisponibilização de Questionário";

        $this->salvarHistorico($redisponibilidade->co_conf_questionario_pai, $dsAcao);
    }

    /**
     * Regras de negocio para Lista as perguntas de acordo com o filtro informado.
     *
     * @param stdClass $filtros
     * @return array
     */
    public function listaConfiguracaoQuestionario(stdClass $filtroPagina)
    {
        return $this->getConfiguracaoQuestionarioDAO()->listaConfiguracaoQuestionario($filtroPagina);
    }

    /**
     * Retorna a disponibilidade conforme o código da configuração informada, e retorna os meses formatados.
     *
     * @param integer $coConfiguracaoQuestionario
     * @return array
     */
    public function getMesesDisponibilidadePorConfiguracao($coConfiguracaoQuestionario)
    {
        $meses = [];
        $disponibilidades = $this->getConfiguracaoQuestionarioDAO()->getMesesDisponibilidadePorConfiguracao($coConfiguracaoQuestionario);

        foreach ($disponibilidades as $disponibilidade) {
            $meses[] = FormataData::getMesPorExtensoAno($disponibilidade->nu_mes_ano);
        }

        return $meses;
    }

    /**
     * Retorna a disponibilidade formatada de acordo a array de meses passada para gerar o CSV
     *
     * @param array $nuMesesAnosDisponibilidade
     * @return string
     */
    public function getMesAnoDisponibilidadeFormatado($nuMesesAnosDisponibilidade)
    {
        $meses = [];
        $nuMesesAnos = explode(',', $nuMesesAnosDisponibilidade);

        foreach ($nuMesesAnos as $mesAno) {
            $meses[] = FormataData::getMesPorExtensoAno($mesAno);
        }

        return $meses = implode(', ', $meses);
    }

    /**
     * Retorna os meses/anos de todos questionários configurados de acordo a iniciativa informada.
     *
     * @param integer $coTipoIniciativa
     * @return array
     */
    public function getMesAnoDisponibilidadeQuestionarioPorIniciativa($coTipoIniciativa)
    {
        $disponibilidades = $this->getConfiguracaoQuestionarioDAO()->getMesAnoDisponibilidadeQuestionarioPorIniciativa($coTipoIniciativa);
        $mesAnos = [
            '' => 'SELECIONE...'
        ];

        foreach ($disponibilidades as $disponibilidade) {
            $mesAnos[$disponibilidade->nu_mes_ano] = FormataData::getMesPorExtensoAno($disponibilidade->nu_mes_ano);
        }

        return $mesAnos;
    }

    /**
     * Recupera a data minima e máxima na qual o questionário poderá ficar vigente de acordo com o código para busca
     * (MEDIOTEC e SISUTEC => $coEdital, PRONATEC_VOLUNTARIO => $coLote) e as turmas.
     *
     * @param $iniciativa
     * @param $coBuscarDisponibilidades
     * @param null $turmas
     * @return object|stdClass
     * @throws Erro
     */
    public function getDataMinimaMaximaPeriodoDisponibilidade($iniciativa, $coBuscarDisponibilidades, $turmas = null)
    {
        $dataMinMax = new stdClass();

        if (!empty($coBuscarDisponibilidades) && empty($turmas)) {
            $dataMinMax = $this->getAdesaoSisuTecnicoEditalBO()->getDatasMinimaMaximaTurmasPorCoBusca($coBuscarDisponibilidades, $iniciativa);
        }

        if (!empty($turmas)) {
            $dataMinMax = $this->getAdesaoSisuTecnicoTurmaBO()->getDataMinimaMaximaPorTurmas($turmas);
        }

        return $dataMinMax;
    }

    /**
     * Verifica a quantidade de configurações vinculada a um questionário.
     *
     * @param integer $coQuestionario
     * @throws Erro
     */
    public function getConfiguracaoPorQuestionario($coQuestionario)
    {
        if (empty($coQuestionario)) {
            throw new Erro('Parâmentros informados inválidos.');
        }

        return $this->getConfiguracaoQuestionarioDAO()->getConfiguracaoPorQuestionario($coQuestionario);
    }

    /**
     * Recupera o codigo do edital e o codigo do lote de acordo com o codigo da configuracao do questionario informada.
     *
     * @param integer $coConfiguracaoQuestionario
     * @return object
     */
    public function getEditalLotePorConfiguracao($coConfiguracaoQuestionario)
    {
        if (empty($coConfiguracaoQuestionario)) {
            throw new Erro('Parâmentros informados inválidos.');
        }

        return $this->getConfiguracaoQuestionarioDAO()->getEditalLotePorConfiguracao($coConfiguracaoQuestionario);
    }

    /**
     * Regras de negócios para verificar a configuração de questionario e alterar a situação de forma automática
     */
    public function atualizarStatusAutomaticoDisponibilidade()
    {
        $configuracaoQuestionarioTO = $this->getConfiguracaoQuestionarioDAO()->getConfiguracaoQuestionarioSituacaoStatus();

        foreach ($configuracaoQuestionarioTO as $configuracaoQuestionario) {
            $this->atualizarStatusDisponibilidade($configuracaoQuestionario);
        }
    }

    /**
     * Método responsável por recuperar informativos e enviar email para seu publico alvo.
     *
     * @return array
     */
    public function enviarEmailInformativoQuestionarioVigente()
    {
        $limit = ini_get('max_execution_time');
        set_time_limit(0);

        $result = [];
        $configuracoes = $this->getConfiguracaoQuestionarioDAO()->getInformativosQuestionarioEnvioEmail();

        foreach ($configuracoes as $configuracao) {
            try {
                $this->enviarInformativoPorEmail($configuracao);
            } catch (Exception $e) {
                $result[$configuracao->co_configuracao_questionario] = $e->getMessage();
            }
        }

        set_time_limit($limit);
        return $result;
    }

    /**
     * Recupera os dados da frente para a configuração de questionário informada.
     *
     * @param int $coConfiguiracaoQuestionario
     * @param int $coFrente
     * @return array
     */
    public function getDadosFrente($coConfiguracaoQuestionario, $coFrente)
    {
        $dadosFrente = null;

        if ($coFrente == ConfiguracaoQuestionarioBO::FRENTE_QUESTIONARIO_CURSO) {
            $dadosFrente = $this->getConfiguracaoQuestionarioFrenteCursoBO()->getConfiguracaoQuestionarioFrenteCursoPorConfiguracaoQuestionario($coConfiguracaoQuestionario);
        }

        if ($coFrente == ConfiguracaoQuestionarioBO::FRENTE_QUESTIONARIO_TURMA) {
            $dadosFrente = $this->getConfiguracaoQuestionarioFrenteTurmaBO()->getConfiguracaoQuestionarioFrenteTurmaPorConfiguracaoQuestionario($coConfiguracaoQuestionario);
        }

        if ($coFrente == ConfiguracaoQuestionarioBO::FRENTE_QUESTIONARIO_UNIDADE_ENSINO) {
            $dadosFrente = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()->getConfiguracaoQuestionarioFrenteUnidadeEnsinoPorConfiguracaoQuestionario($coConfiguracaoQuestionario);
        }

        if ($coFrente == ConfiguracaoQuestionarioBO::FRENTE_QUESTIONARIO_EIXO_TECNOLOGICO) {
            $dadosFrente = $this->getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()->getConfiguracaoQuestionarioFrenteEixoTecnologicoPorConfiguracaoQuestionario($coConfiguracaoQuestionario);
        }

        return $dadosFrente;
    }

    /**
     * Recupera os meses ano que o usuário removeu no alterar configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     * @return array
     */
    public function getNuMesAnoRemovidos(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $mesAnoDisponibilidade = array();
        $nuMesAnoDisponibilidadesRemovidas = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->disponibilidades_nao_selecionadas;
        $configuracaoDisponibilidades = $this->getConfiguracaoQuestionarioDisponibilidadeBO()->getConfiguracaoQuestionarioDisponibilidadesPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoDisponibilidades as $confDisponibilidade) {
            $mesAnoDisponibilidade[] = $confDisponibilidade->nu_mes_ano;
        }

        if (!empty($mesAnoDisponibilidade) && !empty($listaItensNaoSelecionados)) {
            $nuMesAnoDisponibilidadesRemovidas = array_intersect($mesAnoDisponibilidade, $listaItensNaoSelecionados);
        }

        return $nuMesAnoDisponibilidadesRemovidas;
    }

    /**
     * Recupera as regioes que o usuário removeu no alterar configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     * @return array
     */
    public function getRegioesRemovidas(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $coRegioes = array();
        $coRegioesRemovidas = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->regioes_nao_selecionadas;
        $configuracaoRegioes = $this->getConfiguracaoQuestionarioRegiaoBO()->getPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoRegioes as $confRegioes) {
            $coRegioes[] = $confRegioes->co_regiao;
        }

        if (!empty($coRegioes) && !empty($listaItensNaoSelecionados)) {
            $coRegioesRemovidas = array_intersect($coRegioes, $listaItensNaoSelecionados);
        }

        return $coRegioesRemovidas;
    }

    /**
     * Recupera os estados(ufs) que o usuário removeu no alterar configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     * @return array
     */
    public function getUfsRemovidas(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $coUfs = array();
        $coUfsRemovidas = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->ufs_nao_selecionadas;
        $configuracaoUfs = $this->getConfiguracaoQuestionarioUfBO()->getConfiguracaoQuestionarioUfPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoUfs as $confUf) {
            $coUfs[] = $confUf->sg_uf;
        }

        if (!empty($coUfs) && !empty($listaItensNaoSelecionados)) {
            $coUfsRemovidas = array_intersect($coUfs, $listaItensNaoSelecionados);
        }

        return $coUfsRemovidas;
    }

    /**
     * Recupera os municipios que o usuário removeu no alterar configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     * @return array
     */
    public function getMunicipiosRemovidos(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $coMunicipios = array();
        $coMunicipiosRemovidos = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->municipios_nao_selecionados;
        $configuracaoMunicipios = $this->getConfiguracaoQuestionarioMunicipioBO()->getConfiguracaoQuestionarioMunicipioPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoMunicipios as $confMunicipio) {
            $coMunicipios[] = $confMunicipio->co_municipio;
        }

        if (!empty($coMunicipios) && !empty($listaItensNaoSelecionados)) {
            $coMunicipiosRemovidos = array_intersect($coMunicipios, $listaItensNaoSelecionados);
        }

        return $coMunicipiosRemovidos;
    }

    /**
     * Recuperar Abrangencia da configuraçao do questionário.
     *
     * @param object $configuracao
     * @return StdClass
     */
    public function getAbrangenciasPorConfiguracao($configuracao)
    {
        $abrangenciaTO = $this->getConfiguracaoQuestionarioDAO()->getDadosAbrangencia($configuracao->co_configuracao_questionario);
        $abrangenciaTO->no_publico_alvo = $this->nomePublicoAlvo[$configuracao->tp_publico_alvo];
        $abrangenciaTO->no_frente = $this->nomeFrenteQuestionario[$configuracao->tp_frente];
        $abrangenciaTO->regiao = $this->getConfiguracaoQuestionarioRegiaoBO()->getPorConfiguracaoQuestionario($configuracao->co_configuracao_questionario);
        $abrangenciaTO->estados = $this->getConfiguracaoQuestionarioUfBO()->getConfiguracaoQuestionarioUfPorConfiguracaoQuestionario($configuracao->co_configuracao_questionario);
        $abrangenciaTO->municipios = $this->getConfiguracaoQuestionarioMunicipioBO()->getConfiguracaoQuestionarioMunicipioPorConfiguracaoQuestionario($configuracao->co_configuracao_questionario);
        $abrangenciaTO->unidadeEnsinoRemota = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()->getPorConfiguracaoQuestionario($configuracao->co_configuracao_questionario);
        $abrangenciaTO->dadosFrente = $this->getDadosFrente($configuracao->co_configuracao_questionario, $configuracao->tp_frente);

        return $abrangenciaTO;
    }

    /**
     * Recuperar Disponibilidade e Informativos da configuraçao do questionário.
     *
     * @param object $configuracao
     * @return StdClass
     */
    public function getInformativosDisponibilidadePorConfiguracao($configuracao)
    {
        $dadosTO = new StdClass();
        $dadosTO->disponibilidade = $this->getMesesDisponibilidadePorConfiguracao($configuracao->co_configuracao_questionario);
        if ($configuracao->co_info) {
            $dadosTO->informativo = $this->getInformativoBO()->getInformativo($configuracao->co_info);
        }

        return $dadosTO;
    }

    /**
     * Valida se a redisponibilização está disponível para a configuração de questionário informada.
     *
     * @param $coConfiguracaoQuestionario
     * @throws Aviso
     * @throws Zend_Db_Select_Exception
     */
    public function verificarQuantidadeRedisponibilizacoes($coConfiguracaoQuestionario)
    {
        $nuRedisponibilizacoes = $this->getConfiguracaoQuestionarioDAO()->getCountRedisponibilizacoes($coConfiguracaoQuestionario);

        if ($nuRedisponibilizacoes == 2) {
            throw new Aviso('Não é possível redisponibilizar o questionário, ele já foi redisponibilizado duas vezes que é a quantidade máxima de redisponibilização');
        }
    }

    /**
     * Válida o status da configuração de questionário para permitir a redisponibilização do questionário.
     *
     * @param boolean $status
     * @param string $baseUrl
     * @throws Aviso
     */
    public function verificarStatusConfiguracaoRedisponibilizacao($status, $baseUrl)
    {
        if (!$status) {
            throw new Aviso("Não é possível redisponibilizar o questionário com o status da configuração Inativa. Para ativar o questionário acione <img src=\"{$baseUrl}/publico/img/botoes/load.png\">");
        }
    }

    /**
     * Verifica se a redisponibilidade do questionário permite alteração de acordo com sua situação.
     *
     * @param integer $situacao
     * @param boolean $ativo
     * @throws Erro
     */
    public function verificarSituacaoAlteracaoRedisponibilizacao($situacao, $ativo)
    {
        if ($situacao == static::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && $ativo) {
            throw new Aviso('Não é possível alterar uma redisponibilização vigente. Em caso de emergência, inative o questionário!');
        }

        if ($situacao == static::TP_SITUACAO_DISPONIVEL_FINALIZADO) {
            throw new Aviso('Não é possível alterar uma redisponibilização Finalizada.');
        }
    }

    /**
     * Verifica se existe redisponibilizações vinculadas a configuração de questionário informada.
     *
     * @param $coConfiguracao
     * @throws Erro
     * @throws Zend_Db_Select_Exception
     */
    public function verificarExisteRedisponibilizacaoVinculadaAlteracao($coConfiguracao)
    {
        if ($this->getCountRedisponibilizacoes($coConfiguracao) > 0) {
            throw new Aviso('Não é permitido realizar alterações de um questionário que possui redisponibilizações.Caso necessário, remova as redisponibilizações para proceder com a alteração do questionário');
        }
    }

    /**
     * Retorna as redisponibilidades da configuração do questionario de acordo o id passado para ser utilizado na Grid.
     *
     * @param integer $coConfiguracaoQuestionario
     * @return array
     * @throws Erro
     */
    public function getRedisponibilidadePorConfiguracao($coConfiguracaoQuestionario)
    {
        $redisponibilidades = new stdClass();
        $redisponibilidades = $this->getConfiguracaoQuestionarioDAO()->getRedisponibilizacoesPorConfiguracao($coConfiguracaoQuestionario);

        if (!empty($redisponibilidades)) {
            $this->getIconeRedisponibilidade($redisponibilidades);
            return $redisponibilidades;
        }
    }

    /**
     * Recupera os meses anos removidos da redisponibilidade informada.
     *
     * @param $disponibilidadesSelecionadas
     * @param $coRedisponibilidade
     * @return array
     * @throws Erro
     */
    public function getMesesAnoRemovidosRedisponibilidade($disponibilidadesSelecionadas, $coRedisponibilidade)
    {
        $disponibilidades = $this->getConfiguracaoQuestionarioDisponibilidadeBO()->getConfiguracaoQuestionarioDisponibilidadesPorConfiguracaoQuestionario($coRedisponibilidade);
        $mesesAno = array();

        foreach ($disponibilidades as $disponibilidade) {
            $mesesAno[] = intval($disponibilidade->nu_mes_ano);
        }

        foreach ($disponibilidadesSelecionadas as $disponibilidade) {
            $dispSelecionadas[] = intval($disponibilidade);
        }

        return array_diff($mesesAno, $dispSelecionadas);
    }

    /**
     * Recupera o id da ultima reconfiguração vinculada a configuração informada.
     *
     * @param $coConfiguracao
     * @return mixed
     * @throws Zend_Db_Select_Exception
     */
    public function getUltimaReconfiguracaoPorConfiguracao($coConfiguracao)
    {
        return $this->getConfiguracaoQuestionarioDAO()->getUltimaReconfiguracaoPorConfiguracao($coConfiguracao);
    }

    /**
     * Regras de negócio para definir qual icone aparecer de acordo a situação da configuração do questionário.
     *
     * @param stdClass $redisponibilidades
     */
    private function getIconeRedisponibilidade($redisponibilidades)
    {
        $primeiraDisponibilidade = reset($redisponibilidades);
        $segundaDisponibilidade = count($redisponibilidades) == 2 ? end($redisponibilidades) : null;

        if ($primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && empty($segundaDisponibilidade) || $primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && $segundaDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO || $primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && $segundaDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO || $primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO && $segundaDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA || $primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO && $segundaDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA) {
            $primeiraDisponibilidade->cor = self::COR_VERDE;
        }

        if ($primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO && empty($segundaDisponibilidade) || $primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO && $segundaDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO || $primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO && $segundaDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO) {
            $primeiraDisponibilidade->cor = self::COR_AZUL;
        }

        if ($primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO && empty($segundaDisponibilidade) || $primeiraDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO && $segundaDisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO) {
            $primeiraDisponibilidade->cor = self::COR_VERMELHO;
        }

        if (empty($primeiraDisponibilidade->st_ativo) && empty($segundaDisponibilidade) || empty($primeiraDisponibilidade->st_ativo) && empty($segundaDisponibilidade->st_ativo)) {
            $primeiraDisponibilidade->cor = self::COR_CINZA;
        }
    }

    /**
     * Verifica se existem ainda meses disponiveis para a redisponibilização do questionário.
     *
     * @param $coConfiguracaoQuestionario
     * @param $coIniciativa
     * @throws Aviso
     * @throws Erro
     * @throws Zend_Db_Select_Exception
     */
    public function verificarExisteMesesDisponiveisRedisponibilizacao($coConfiguracaoQuestionario, $coIniciativa)
    {
        $configuracaoQuestionario = $this->getConfiguracaoQuestionario($coConfiguracaoQuestionario);
        $turmas = $this->getConfiguracaoQuestionarioFrenteTurmaBO()->getConfiguracaoQuestionarioFrenteTurmaPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($turmas as $turma) {
            $coTurmas[] = ($configuracaoQuestionario->co_tipo_iniciativa != ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO) ? $turma->co_turma : $turma->co_turma_pactuacao;
        }

        $dataAtual = Utils::getDataToString(date(Utils::FORMATO_DATA_BD), Utils::FORMATO_DATA_BD);
        $dataAtual->modify('first day of this month');

        if ($coIniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO) {
            $configuracaoPactuacao = $this->getConfiguracaoQuestionarioPactuacaoBO()->getConfiguracaoPactuacaoPorConfQuestionario($coConfiguracaoQuestionario);
            $periodoDisponibilidade = $this->getConfiguracaoQuestionarioPactuacaoBO()->getDataMinimaMaximaPeriodoDisponibilidade($configuracaoPactuacao->co_pactuacao_periodo, $coTurmas);
            $dataMaxima = Utils::getDataToString($periodoDisponibilidade->dt_maxima, Utils::FORMATO_DATA_BD);
        } else {
            $coBuscaDisponibilidades = $configuracaoQuestionario->co_adesao_sisu_tecnico_edital;

            if ($coIniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PRONATEC_VOLUNTARIO) {
                $coBuscaDisponibilidades = $configuracaoQuestionario->co_adesao_edital_lote;
            }

            $periodoDisponibilidade = $this->getDataMinimaMaximaPeriodoDisponibilidade($coIniciativa, $coBuscaDisponibilidades, $coTurmas);
            $dataMaxima = Utils::getDataToString($periodoDisponibilidade->dt_maxima, Utils::FORMATO_TIMESTAMP_BD);
        }

        $dataMaxima = Utils::getDataHoraZero($dataMaxima);
        $dataMaxima->modify('last day of this month');

        $maiorMesAno = $this->getConfiguracaoQuestionarioDisponibilidadeBO()->getMaiorMesAnoConfiguracaoRedisponibilizacao($coConfiguracaoQuestionario);
        $maiorMesAno = Utils::getDataHoraZero(Utils::getDataToString($maiorMesAno, Utils::FORMATO_DATA_MES_ANO));
        $maiorMesAno = $maiorMesAno->modify('last day of this month');

        if ($dataAtual > $dataMaxima || $maiorMesAno == $dataMaxima) {
            throw new Aviso('Não há período de disponibilidade suficiente para redisponibilização do questionário!');
        }
    }

    /**
     * Recupera a lista de todas as disponibilidades disponíveis para redisponibilização.
     *
     * @param object $configuracaoQuestionario
     * @param integer $iniciativa
     * @return array
     * @throws Erro
     * @throws Zend_Date_Exception
     * @throws Zend_Db_Select_Exception
     */
    public function getDisponibilidadesRedisponibilizacao($configuracaoQuestionario, $iniciativa)
    {
        $isPactuacao = $iniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO;

        $nuMesAnoDisponiveis = array();
        $coBuscaDisponibilidades = $configuracaoQuestionario->co_adesao_sisu_tecnico_edital;
        $turmas = $this->getConfiguracaoQuestionarioFrenteTurmaBO()->getConfiguracaoQuestionarioFrenteTurmaPorConfiguracaoQuestionario($configuracaoQuestionario->co_configuracao_questionario);

        foreach ($turmas as $turma) {
            $coTurmas[] = ($configuracaoQuestionario->co_tipo_iniciativa != ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO) ? $turma->co_turma : $turma->co_turma_pactuacao;
        }

        if ($configuracaoQuestionario->co_tipo_iniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PRONATEC_VOLUNTARIO) {
            $coBuscaDisponibilidades = $configuracaoQuestionario->co_adesao_edital_lote;
        }

        $disponibilidadesConfiguracao = $this->getConfiguracaoQuestionarioDisponibilidadeBO()->getConfiguracaoQuestionarioDisponibilidadesPorConfiguracaoQuestionario($configuracaoQuestionario->co_configuracao_questionario);
        foreach ($disponibilidadesConfiguracao as $disponibilidade) {
            $nuMesAnoDisponiveis[] = $disponibilidade->nu_mes_ano;
        }

        $disponibilidadesRedisponibilizacao = $this->getConfiguracaoQuestionarioDisponibilidadeBO()->getDisponibilidadesRedisponibilizacaoPorConfQuestionario($configuracaoQuestionario->co_configuracao_questionario);
        foreach ($disponibilidadesRedisponibilizacao as $disponibilidade) {
            $nuMesAnoDisponiveis[] = $disponibilidade->nu_mes_ano;
        }

        if ($configuracaoQuestionario->co_tipo_iniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO) {
            $periodoDisponibilidade = $this->getConfiguracaoQuestionarioPactuacaoBO()->getDataMinimaMaximaPeriodoDisponibilidade($configuracaoQuestionario->co_pactuacao_periodo, $coTurmas);
        } else {
            $periodoDisponibilidade = $this->getDataMinimaMaximaPeriodoDisponibilidade($configuracaoQuestionario->co_tipo_iniciativa, $coBuscaDisponibilidades, $coTurmas);
        }

        return $this->getMesAnosDisponiveisRedisponibilizacao($periodoDisponibilidade->dt_minima,
            $periodoDisponibilidade->dt_maxima, $nuMesAnoDisponiveis, $isPactuacao);
    }

    /**
     * Recupera o número de redisponiblizações.
     *
     * @param $coConfQuestionario
     * @return mixed
     * @throws Zend_Db_Select_Exception
     */
    public function getCountRedisponibilizacoes($coConfQuestionario)
    {
        return $this->getConfiguracaoQuestionarioDAO()->getCountRedisponibilizacoes($coConfQuestionario);
    }

    /**
     * Recupera o questionário dinâmico pelo código da configuração informado.
     *
     * @param $coConfiguracao
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    public function getQuestionarioPorConfiguracao($coConfiguracao)
    {
        return $this->getConfiguracaoQuestionarioDAO()->getQuestionarioPorConfiguracao($coConfiguracao);
    }

    /**
     * Recupera a lista de disponibilidades para a redisponibilização de configuração de questionário.
     *
     * @param $dataInicio
     * @param $dataFim
     * @param $disponibilidadesCadastradas
     * @return array
     */
    private function getMesAnosDisponiveisRedisponibilizacao($dataInicio, $dataFim, $disponibilidadesCadastradas, $isPactuacao = false) {
        $disponibilidades = array();
        $dataAtual = Utils::getData();
        $dataAtual->modify('first day of this month');

        if ($isPactuacao) {
            $dataFim = Utils::getDataToString($dataFim, Utils::FORMATO_DATA_BD, Utils::TIMEZONE_PADRAO);
            $dataInicio = Utils::getDataToString($dataInicio, Utils::FORMATO_DATA_BD, Utils::TIMEZONE_PADRAO);
        } else {
            $dataFim = Utils::getDataToString($dataFim, Utils::FORMATO_TIMESTAMP_BD, Utils::TIMEZONE_PADRAO);
            $dataInicio = Utils::getDataToString($dataInicio, Utils::FORMATO_TIMESTAMP_BD, Utils::TIMEZONE_PADRAO);
        }

        $ultimaDisponibilidadeCadastrada = $this->getPeriodoMesAnoDisponibilidadeSelecionadas($disponibilidadesCadastradas)->maiorDisponibilidade;
        $ultimaDisponibilidadeCadastrada = Utils::getDataToString($ultimaDisponibilidadeCadastrada, 'mY', Utils::TIMEZONE_PADRAO);
        $ultimaDisponibilidadeCadastrada->modify('first day of this month');
        $ultimaDisponibilidadeCadastrada->modify('+1 month');

        if (empty($dataInicio) || empty($dataFim)) {
            return $disponibilidades;
        }

        if ($ultimaDisponibilidadeCadastrada < $dataAtual) {
            $ultimaDisponibilidadeCadastrada = $dataAtual;
        }

        if ($ultimaDisponibilidadeCadastrada < $dataInicio) {
            while ($dataInicio < $dataFim) {
                $disponibilidadeTO = new stdClass();
                $disponibilidadeTO->key = $ultimaDisponibilidadeCadastrada->format('mY');
                $disponibilidadeTO->value = utf8_encode(strtoupper(strftime("%B/%Y",
                    $ultimaDisponibilidadeCadastrada->getTimestamp())));

                $disponibilidades[] = $disponibilidadeTO;
                $ultimaDisponibilidadeCadastrada->modify('+1 month');
            }
        } else {
            while ($ultimaDisponibilidadeCadastrada < $dataFim) {
                $disponibilidadeTO = new stdClass();
                $disponibilidadeTO->key = $ultimaDisponibilidadeCadastrada->format('mY');
                $disponibilidadeTO->value = utf8_encode(strtoupper(strftime("%B/%Y",
                    $ultimaDisponibilidadeCadastrada->getTimestamp())));

                $disponibilidades[] = $disponibilidadeTO;
                $ultimaDisponibilidadeCadastrada->modify('+1 month');
            }
        }

        return $disponibilidades;
    }

    /**
     * Recupera o nome da frente do questionário.
     *
     * @param $tpFrente
     * @return mixed
     */
    public function getNomeFrente($tpFrente)
    {
        return $this->nomeFrenteQuestionario[$tpFrente];
    }

    /**
     * Recupera o código do publico alvo.
     *
     * @param $coConfiguracao
     * @return int
     */
    public function getTpPublicoAlvo($coConfiguracao)
    {
        return $this->getConfiguracaoQuestionarioDAO()->getTpPublicoAlvo($coConfiguracao);
    }

    /**
     * Recupera o código da iniciativa.
     *
     * @param $coConfiguracao
     * @return int
     */
    public function getIniciativa($coConfiguracao)
    {
        return $this->getConfiguracaoQuestionarioDAO()->getIniciativa($coConfiguracao);
    }

    /**
     * Método responsável por enviar e-mail de acordo o publico alvo da configuração do informativo passado.
     *
     * @param stdClass $configuracao
     */
    private function enviarInformativoPorEmail($configuracao)
    {
        try {
            $parametro = $this->getParametrosConfiguracaoBO()->getParametroPorNome('limite_resultado_emails_questionarios');
            $configuracao->limit = Utils::getValue('ds_valor', $parametro);

            switch ($configuracao->tp_publico_alvo) {
                case ConfiguracaoInformativoModel::TP_PUBLICO_ALVO_ALUNOS:

                    if ($configuracao->co_tipo_iniciativa != self::TP_INICIATIVA_PACTUACAO) {
                        $alunos = $this->getConfiguracaoQuestionarioDAO()->getEmailAlunosPorAbrangenciaQuestionario($configuracao);
                    } else {
                        $alunos = $this->getConfiguracaoQuestionarioDAO()->getEmailAlunosPactuacaoPorAbrangenciaQuestionario($configuracao);
                    }

                    $this->enviarEmailsQuestionario($configuracao, $alunos);
                    break;
                case ConfiguracaoInformativoModel::TP_PUBLICO_ALVO_UNIDADES_ENSINO:

                    if ($configuracao->co_tipo_iniciativa != self::TP_INICIATIVA_PACTUACAO) {
                        $unidadesEnsino = $this->getConfiguracaoQuestionarioDAO()->getEmailPessoasUnidadesEnsinosPorAbrangenciaQuestionario($configuracao);
                    } else {
                        $unidadesEnsino = $this->getConfiguracaoQuestionarioDAO()->getEmailUnidadesEnsinosPactuacaoPorAbrangenciaQuestionario($configuracao);
                    }

                    $this->enviarEmailsQuestionario($configuracao, $unidadesEnsino);
                    break;
                case ConfiguracaoInformativoModel::TP_PUBLICO_ALVO_MANTENEDOR:

                    if ($configuracao->co_tipo_iniciativa != self::TP_INICIATIVA_PACTUACAO) {
                        $mantenedoras = $this->getEmailsMantenedoraPorAbrangenciaQuestionario($configuracao);
                    } else {
                        $mantenedoras = $this->getEmailsMantenedoraPactuacaoPorAbrangenciaQuestionario($configuracao);
                    }

                    $this->enviarEmailsQuestionario($configuracao, $mantenedoras);
                    break;
            }

            $configuracao->dt_email_enviado = Utils::getData();
            $configuracao->dt_email_enviado = $configuracao->dt_email_enviado->format(Utils::FORMATO_TIMESTAMP_BD);
        } catch (Exception $e) {
            $configuracao->nu_tentativa_email = empty($configuracao->nu_tentativa_email) ? 1 : $configuracao->nu_tentativa_email++;
            throw new Erro($e);
        } finally {
            unset($configuracao->ds_info);
            unset($configuracao->limit);
            $this->getConfiguracaoQuestionarioDAO()->salvarConfiguracaoQuestionario($configuracao);
        }
    }

    /**
     * Método responsável por montar o corpo do e-mail do informátivo
     *
     * @param string $nome
     * @param string $dsEmail
     * @param string $dsInfo
     */
    private function getCorpoEmail($nome, $dsInfo)
    {
        $html = "<p>Prezado(a) {$nome}</p>
                 <p> {$dsInfo} </p>
                 <small> <p>Este e-mail não deve ser respondido.</p></small>
                 <small>
                        <p><b>SISTEMA NACIONAL DE INFORMAÇÕES DA EDUCAÇÃO PROFISSIONAL E TECNOLÓGICA - SISTEC</b></br>
                        <b>Ministério da Educação</b></br>
                        <b>Secretaria de Educação Profissional e Tecnológica</b></br>
                        <b>Programa Nacional de Acesso ao Ensino Técnico e Emprego</b></p></br>
                 </small>";

        return $html;
    }

    /**
     * Salva uma configuraçao de questionario pactuaçao.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @return object $configuracaoPactuacao
     */
    private function salvarConfiguracaoQuestionarioPactuacao(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario) {
        $configuracaoPactuacao = new stdClass();
        $configuracaoPactuacao->co_configuracao_questionario = $coConfiguracaoQuestionario;
        $configuracaoPactuacao->co_pactuacao_periodo = $configuracaoQuestionarioTO->co_pactuacao_periodo;
        $configuracaoPactuacao->nu_ano_pactua_periodo = $configuracaoQuestionarioTO->nu_ano_pactua_periodo;
        $configuracaoPactuacao->co_configuracao_questionario_pactuacao = $configuracaoQuestionarioTO->co_configuracao_pactuacao;
        $configuracaoPactuacao->co_modalidade_demanda = ($configuracaoQuestionarioTO->co_modalidade_demanda) ? $configuracaoQuestionarioTO->co_modalidade_demanda : null;
        $configuracaoPactuacao->co_tipo_curso_tecnico = null;

        if ($configuracaoQuestionarioTO->co_tipo_curso_tecnico && $configuracaoQuestionarioTO->co_tipo_curso == Sistec_Constantes::CO_TIPO_CURSO_TECNICO) {
            $configuracaoPactuacao->co_tipo_curso_tecnico = $configuracaoQuestionarioTO->co_tipo_curso_tecnico;
        }

        return $this->getConfiguracaoQuestionarioPactuacaoBO()->salvar($configuracaoPactuacao);
    }

    /**
     * Salva uma nova configuraçao de questionario pactuaçao para a redisponibilizacao informada.
     *
     * @param stdClass $redisponibilizacao
     * @return object
     * @throws Erro
     */
    private function salvarRedisponibilizacaoPactuacao(stdClass $redisponibilizacao)
    {
        $redisponibilizacaoPactuacao = $this->getConfiguracaoQuestionarioPactuacaoBO()->getConfiguracaoPactuacaoPorConfQuestionario($redisponibilizacao->co_conf_questionario_pai);
        $redisponibilizacaoPactuacao->co_configuracao_questionario_pactuacao = null;
        $redisponibilizacaoPactuacao->co_configuracao_questionario = $redisponibilizacao->co_configuracao_questionario;
        unset($redisponibilizacaoPactuacao->ds_pactuacao_periodo);

        return $this->getConfiguracaoQuestionarioPactuacaoBO()->salvar($redisponibilizacaoPactuacao);
    }

    /**
     * Salva um novo valor para o histórico de configuração de questionário.
     *
     * @param int $coConfiguracaoQuestionario
     * @param string $dsAcao
     * @param string $dsJustificativa
     */
    private function salvarHistorico($coConfiguracaoQuestionario, $dsAcao, $dsJustificativa = null)
    {
        $sessao = new Zend_Session_Namespace('Zend_Auth');
        $configuracaoQuestionarioHistorico = new stdClass();
        $configuracaoQuestionarioHistorico->ds_acao = $dsAcao;
        $configuracaoQuestionarioHistorico->ds_justificativa = $dsJustificativa;
        $configuracaoQuestionarioHistorico->co_pessoa_inst_grupo = $sessao->pessoaInstituicaoGrupo;
        $configuracaoQuestionarioHistorico->co_configuracao_questionario = $coConfiguracaoQuestionario;

        $this->getConfiguracaoQuestionarioHistoricoBO()->salvarConfiguracaoQuestionarioHistorico($configuracaoQuestionarioHistorico);
    }

    /**
     * Salva as disponibilidades para a configuracao do questionário.
     *
     * @param array $disponibilidades
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarDisponibilidades($disponibilidades, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioDisponibilidades = array();

        if (empty($disponibilidades)) {
            return null;
        }

        foreach ($disponibilidades as $disponibilidade) {
            $configuracaoQuestionarioDisponibilidade = new stdClass();
            $configuracaoQuestionarioDisponibilidade->nu_mes_ano = $disponibilidade;
            $configuracaoQuestionarioDisponibilidade->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoDisponibilidadeCadastrada = $this->getConfiguracaoQuestionarioDisponibilidadeBO()->getDisponibilidadePorConfiguracaoQuestionarioMesAno($configuracaoQuestionarioDisponibilidade->co_configuracao_questionario, $configuracaoQuestionarioDisponibilidade->nu_mes_ano);
            if (empty($configuracaoDisponibilidadeCadastrada)) {
                $configuracaoQuestionarioDisponibilidades[] = $configuracaoQuestionarioDisponibilidade;
            }
        }

        if (!empty($configuracaoQuestionarioDisponibilidades)) {
            $this->getConfiguracaoQuestionarioDisponibilidadeBO()->salvarConfiguracaoQuestionarioDisponibilidadeEmLote($configuracaoQuestionarioDisponibilidades);
        }
    }

    /**
     * Salva os estados (UF's) da configuracao do questionário.
     *
     * @param array $ufs
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarUfs($ufs, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioUfs = array();

        if (empty($ufs)) {
            return null;
        }

        foreach ($ufs as $uf) {
            $configuracaoQuestionarioUf = new stdClass();
            $configuracaoQuestionarioUf->sg_uf = $uf;
            $configuracaoQuestionarioUf->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoQuestionarioCadastrado = $this->getConfiguracaoQuestionarioUfBO()->getConfiguracaoUfPorConfiguracaoQuestionarioUf($coConfiguracaoQuestionario,
                $uf);
            if (empty($configuracaoQuestionarioCadastrado)) {
                $configuracaoQuestionarioUfs[] = $configuracaoQuestionarioUf;
            }
        }

        if (!empty($configuracaoQuestionarioUfs)) {
            $this->getConfiguracaoQuestionarioUfBO()->salvarConfiguracaoQuestionarioUfEmLote($configuracaoQuestionarioUfs);
        }
    }

    /**
     * Salva os regiões da configuracao do questionário.
     *
     * @param array $regioes
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarRegioes($regioes, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioRegioes = array();

        if (empty($regioes)) {
            return null;
        }

        foreach ($regioes as $regiao) {
            $configuracaoQuestionarioRegiao = new stdClass();
            $configuracaoQuestionarioRegiao->co_regiao = $regiao;
            $configuracaoQuestionarioRegiao->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoRegiaoCadastrada = $this->getConfiguracaoQuestionarioRegiaoBO()->getConfiguracaoRegiaoPorConfiguracaoQuestionarioRegiao($configuracaoQuestionarioRegiao->co_configuracao_questionario, $configuracaoQuestionarioRegiao->co_regiao);
            if (empty($configuracaoRegiaoCadastrada)) {
                $configuracaoQuestionarioRegioes[] = $configuracaoQuestionarioRegiao;
            }
        }

        if (!empty($configuracaoQuestionarioRegioes)) {
            $this->getConfiguracaoQuestionarioRegiaoBO()->salvarConfiguracaoQuestionarioRegiaoEmLote($configuracaoQuestionarioRegioes);
        }
    }

    /**
     * Salva os municípios da configuracao do questionário.
     *
     * @param array $municipios
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarMunicipio($municipios, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioMunicipios = array();

        if (empty($municipios)) {
            return null;
        }

        foreach ($municipios as $municipio) {
            $configuracaoQuestionarioMunicipio = new stdClass();
            $configuracaoQuestionarioMunicipio->co_municipio = $municipio;
            $configuracaoQuestionarioMunicipio->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoMunicipioCadastrada = $this->getConfiguracaoQuestionarioMunicipioBO()->getConfiguracaoMunicipioPorConfiguracaoQuestionarioMunicipio($configuracaoQuestionarioMunicipio->co_configuracao_questionario, $configuracaoQuestionarioMunicipio->co_municipio);
            if (empty($configuracaoMunicipioCadastrada)) {
                $configuracaoQuestionarioMunicipios[] = $configuracaoQuestionarioMunicipio;
            }
        }

        if (!empty($configuracaoQuestionarioMunicipios)) {
            $this->getConfiguracaoQuestionarioMunicipioBO()->salvarConfiguracaoQuestionarioMunicipioEmLote($configuracaoQuestionarioMunicipios);
        }
    }

    /**
     * Responsável por salvar os dados da frente do questionário.
     *
     * @param stdClass $dadosFrente
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarFrente(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        if ($configuracaoQuestionarioTO->co_frente == static::FRENTE_QUESTIONARIO_CURSO) {
            $this->salvarFrenteCurso($configuracaoQuestionarioTO->dados_frente, $coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == static::FRENTE_QUESTIONARIO_TURMA) {
            $this->salvarFrenteTurma($configuracaoQuestionarioTO->dados_frente,
                $configuracaoQuestionarioTO->co_tipo_iniciativa, $coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == static::FRENTE_QUESTIONARIO_UNIDADE_ENSINO) {
            $this->salvarFrenteUnidadeEnsino($configuracaoQuestionarioTO->dados_frente, $coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == static::FRENTE_QUESTIONARIO_UNIDADE_ENSINO && !empty($configuracaoQuestionarioTO->unidades_ensino_remota)) {
            $this->salvarFrenteUnidadeEnsinoRemota($configuracaoQuestionarioTO->unidades_ensino_remota,
                $coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == static::FRENTE_QUESTIONARIO_EIXO_TECNOLOGICO) {
            $this->salvarFrenteEixoTecnologico($configuracaoQuestionarioTO->dados_frente, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável salvar os dados da frente da turma do questionário.
     *
     * @param array $coTurmas
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarFrenteTurma($coTurmas, $tpIniciativa, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioFrenteTurmas = array();

        if (empty($coTurmas)) {
            return null;
        }

        foreach ($coTurmas as $coTurma) {
            $configuracaoQuestionarioFrenteTurma = new stdClass();
            $configuracaoQuestionarioFrenteTurma->co_configuracao_questionario = $coConfiguracaoQuestionario;

            if ($tpIniciativa == static::TP_INICIATIVA_PACTUACAO) {
                $configuracaoQuestionarioFrenteTurma->co_turma_pactuacao = $coTurma;
            } else {
                $configuracaoQuestionarioFrenteTurma->co_turma = $coTurma;
            }

            $configuracaoFrenteTurmaCadastrada = $this->getConfiguracaoQuestionarioFrenteTurmaBO()->getConfiguracaoFrenteTurmaPorConfiguracaoQuestionarioTurma($configuracaoQuestionarioFrenteTurma->co_configuracao_questionario,
                $configuracaoQuestionarioFrenteTurma->co_turma);
            if (empty($configuracaoFrenteTurmaCadastrada)) {
                $configuracaoQuestionarioFrenteTurmas[] = $configuracaoQuestionarioFrenteTurma;
            }
        }

        if (!empty($configuracaoQuestionarioFrenteTurmas)) {
            $this->getConfiguracaoQuestionarioFrenteTurmaBO()->salvarConfiguracaoQuestionarioFrenteTurmaEmLote($configuracaoQuestionarioFrenteTurmas);
        }
    }

    /**
     * Responsável salvar os dados da frente de curso do questionário.
     *
     * @param array $coCursos
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarFrenteCurso($coCursos, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioFrenteCursos = array();

        if (empty($coCursos)) {
            return null;
        }

        foreach ($coCursos as $coCurso) {
            $configuracaoQuestionarioFrenteCurso = new stdClass();
            $configuracaoQuestionarioFrenteCurso->co_curso = $coCurso;
            $configuracaoQuestionarioFrenteCurso->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoFrenteCursoCadastrada = $this->getConfiguracaoQuestionarioFrenteCursoBO()->getConfiguracaFrenteCursoPorConfiguracaoQuestionarioCurso($configuracaoQuestionarioFrenteCurso->co_configuracao_questionario, $configuracaoQuestionarioFrenteCurso->co_curso);
            if (empty($configuracaoFrenteCursoCadastrada)) {
                $configuracaoQuestionarioFrenteCursos[] = $configuracaoQuestionarioFrenteCurso;
            }
        }

        if (!empty($configuracaoQuestionarioFrenteCursos)) {
            $this->getConfiguracaoQuestionarioFrenteCursoBO()->salvarConfiguracaoQuestionarioFrenteCursoEmLote($configuracaoQuestionarioFrenteCursos);
        }

    }

    /**
     * Responsável salvar os dados da frente de unidade de ensino do questionário.
     *
     * @param array $coUnidadesEnsino
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarFrenteUnidadeEnsino($coUnidadesEnsino, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioFrenteUnidadesEnsino = array();

        if (empty($coUnidadesEnsino)) {
            return null;
        }

        foreach ($coUnidadesEnsino as $coUnidadeEnsino) {
            $configuracaoQuestionarioFrenteUnidadeEnsino = new stdClass();
            $configuracaoQuestionarioFrenteUnidadeEnsino->co_unidade_ensino = $coUnidadeEnsino;
            $configuracaoQuestionarioFrenteUnidadeEnsino->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoFrenteUnidadeEnsinoCadastrada = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()->getConfiguracaoFrenteUnidadeEnsinoPorConfiguracaoQuestionarioUnidadeEnsino($configuracaoQuestionarioFrenteUnidadeEnsino->co_configuracao_questionario, $configuracaoQuestionarioFrenteUnidadeEnsino->co_unidade_ensino);
            if (empty($configuracaoFrenteUnidadeEnsinoCadastrada)) {
                $configuracaoQuestionarioFrenteUnidadesEnsino[] = $configuracaoQuestionarioFrenteUnidadeEnsino;
            }
        }

        if (!empty($configuracaoQuestionarioFrenteUnidadesEnsino)) {
            $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()->salvarConfiguracaoQuestionarioFrenteUnidadeEnsinoEmLote($configuracaoQuestionarioFrenteUnidadesEnsino);

        }
    }

    /**
     * Responsável salvar os dados da frente de unidade de ensino remoto do questionário.
     *
     * @param array $coUnidadesEnsino
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarFrenteUnidadeEnsinoRemota($coUnidadeEnsinoRemotas, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioFrenteUnidadeEnsinoRemotas = array();

        if (empty($coUnidadeEnsinoRemotas)) {
            return null;
        }

        foreach ($coUnidadeEnsinoRemotas as $coUnidadeEnsinoRemota) {
            $configuracaoQuestionarioFrenteUnidadeEnsinoRemota = new stdClass();
            $configuracaoQuestionarioFrenteUnidadeEnsinoRemota->co_unidade_ensino_remota = $coUnidadeEnsinoRemota;
            $configuracaoQuestionarioFrenteUnidadeEnsinoRemota->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoFrenteUnidadeEnsinoRemotaCadastrada = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()->getConfiguracaoUnidadeEnsinoRemotaPorConfiguracaoQuestionarioUnidadeEnsinoRemota($configuracaoQuestionarioFrenteUnidadeEnsinoRemota->co_configuracao_questionario, $configuracaoQuestionarioFrenteUnidadeEnsinoRemota->co_unidade_ensino_remota);
            if (empty($configuracaoFrenteUnidadeEnsinoRemotaCadastrada)) {
                $configuracaoQuestionarioFrenteUnidadeEnsinoRemotas[] = $configuracaoQuestionarioFrenteUnidadeEnsinoRemota;
            }
        }

        if (!empty($configuracaoQuestionarioFrenteUnidadeEnsinoRemotas)) {
            $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()->salvarConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaEmLote($configuracaoQuestionarioFrenteUnidadeEnsinoRemotas);
        }
    }

    /**
     * Responsável salvar os dados da frente de eixo tecnológico do questionário.
     *
     * @param array $coEixosTecnologicos
     * @param int $coConfiguracaoQuestionario
     */
    private function salvarFrenteEixoTecnologico($coEixosTecnologicos, $coConfiguracaoQuestionario)
    {
        $configuracaoQuestionarioFrenteEixosTecnologicos = array();

        if (empty($coEixosTecnologicos)) {
            return null;
        }

        foreach ($coEixosTecnologicos as $coEixoTecnologico) {
            $configuracaoQuestionarioFrenteEixoTecnologico = new stdClass();
            $configuracaoQuestionarioFrenteEixoTecnologico->co_eixo_tecnologico = $coEixoTecnologico;
            $configuracaoQuestionarioFrenteEixoTecnologico->co_configuracao_questionario = $coConfiguracaoQuestionario;

            $configuracaoFrenteEixoTecnologicoCadastrado = $this->getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()->getConfiguracaoEixoTecnologicoPorConfiguracaoQuestionarioEixoTecnologico($configuracaoQuestionarioFrenteEixoTecnologico->co_configuracao_questionario, $configuracaoQuestionarioFrenteEixoTecnologico->co_eixo_tecnologico);
            if (empty($configuracaoFrenteEixoTecnologicoCadastrado)) {
                $configuracaoQuestionarioFrenteEixosTecnologicos[] = $configuracaoQuestionarioFrenteEixoTecnologico;
            }
        }

        if (!empty($configuracaoQuestionarioFrenteEixosTecnologicos)) {
            $this->getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()->salvarConfiguracaoQuestionarioFrenteEixoTecnologicoEmLote($configuracaoQuestionarioFrenteEixosTecnologicos);
        }
    }

    /**
     * Salva as esferas administrativas vinculadas a pactuaçao.
     *
     * @param array $esferasAdministrativas
     * @param integer $coConfiguracaoQuestionarioPactuacao
     */
    private function salvarEsferasAdministrativas($esferasAdministrativas, $coConfiguracaoQuestionarioPactuacao)
    {
        $confQuestionarioEsferasAdmin = array();

        if (empty($esferasAdministrativas)) {
            return null;
        }

        foreach ($esferasAdministrativas as $esferaAdministrativa) {
            $confQuestionarioEsferaAdmin = new stdClass();
            $confQuestionarioEsferaAdmin->co_dependencia_admin = $esferaAdministrativa;
            $confQuestionarioEsferaAdmin->co_configuracao_questionario_pactuacao = $coConfiguracaoQuestionarioPactuacao;
            $confQuestionarioEsferasAdmin[] = $confQuestionarioEsferaAdmin;
        }

        $this->getConfiguracaoQuestionarioEsferaAdministrativaBO()->salvarEmLote($confQuestionarioEsferasAdmin);
    }

    /**
     * Salva as esferas administrativas vinculadas a pactuaçao.
     *
     * @param array $subEsferasAdministrativas
     * @param integer $coConfiguracaoQuestionarioPactuacao
     */
    private function salvarSubEsferasAdministrativas($subEsferasAdministrativas, $coConfiguracaoQuestionarioPactuacao)
    {
        $confQuestionarioSubEsferasAdmin = array();

        if (empty($subEsferasAdministrativas)) {
            return null;
        }

        foreach ($subEsferasAdministrativas as $subEsferaAdministrativa) {
            $confQuestionarioSubEsferaAdmin = new stdClass();
            $confQuestionarioSubEsferaAdmin->co_subdependencia_admin = $subEsferaAdministrativa;
            $confQuestionarioSubEsferaAdmin->co_configuracao_questionario_pactuacao = $coConfiguracaoQuestionarioPactuacao;
            $confQuestionarioSubEsferasAdmin[] = $confQuestionarioSubEsferaAdmin;
        }

        $this->getConfiguracaoQuestionarioSubEsferaAdministrativaBO()->salvarEmLote($confQuestionarioSubEsferasAdmin);
    }

    /**
     * Responsável por remover as disponibilidades que o usuário removeu na alteração de configuração de questionário.
     *
     * @param array $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirDisponibilidades($disponibilidadesRemovidas, $coConfiguracaoQuestionario)
    {
        if (!empty($disponibilidadesRemovidas)) {
            $this->getConfiguracaoQuestionarioDisponibilidadeBO()->excluirEmLotePorMesesAnoConfiguracao($disponibilidadesRemovidas,
                $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover os estados(ufs) que o usuário removeu na alteração de configuração de questionário.
     *
     * @param array $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirUfs($regioesRemovidas, &$ufsRemovidas, $coConfiguracaoQuestionario)
    {
        if (!empty($regioesRemovidas)) {
            $ufsVinculadasRegioesRemovidas = $this->getEstadosBO()->getCoEstadoPorRegioes($regioesRemovidas);
        }

        if (!empty($ufsVinculadasRegioesRemovidas)) {
            $configuracoesUf = $this->getConfiguracaoQuestionarioUfBO()->getConfUfsPorUfsConfiguracaoQuestionario($ufsVinculadasRegioesRemovidas, $coConfiguracaoQuestionario);
        }

        if (!empty($configuracoesUf)) {
            foreach ($configuracoesUf as $configuracaoUf) {
                $ufsRemovidas[] = $configuracaoUf->sg_uf;
            }
        }

        if (!empty($ufsRemovidas)) {
            $this->getConfiguracaoQuestionarioUfBO()->excluirEmLotePorUfsConfiguracao($ufsRemovidas, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover as regiões que o usuário removeu na alteração de configuração de questionário.
     *
     * @param array $regioesRemovidas
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirRegioes($regioesRemovidas, $coConfiguracaoQuestionario)
    {
        if (!empty($regioesRemovidas)) {
            $this->getConfiguracaoQuestionarioRegiaoBO()->excluirEmLotePorRegiaoConfiguracaoQuestionario($regioesRemovidas, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover os municipios que o usuário removeu na alteração de configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirMunicipios($ufsRemovidas, $municipiosRemovidos, $coConfiguracaoQuestionario)
    {
        if (!empty($ufsRemovidas)) {
            $municipiosVinculadosUfsRemovidas = $this->getMuncipiosBO()->getCoMunicipiosPorEstados($ufsRemovidas);
        }

        if (!empty($municipiosVinculadosUfsRemovidas)) {
            $configuracoesMunicipio = $this->getConfiguracaoQuestionarioMunicipioBO()->getConfMunicipiosPorMunicipiosConfiguracaoQuestionario($municipiosVinculadosUfsRemovidas, $coConfiguracaoQuestionario);
        }

        if (!empty($configuracoesMunicipio)) {
            foreach ($configuracoesMunicipio as $configuracaoMunicipio) {
                $municipiosRemovidos[] = $configuracaoMunicipio->co_municipio;
            }
        }

        if (!empty($municipiosRemovidos)) {
            $this->getConfiguracaoQuestionarioMunicipioBO()->excluirEmLotePorMunicipioConfiguracaoQuestionario($municipiosRemovidos, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover os dados de frente que o usuário removeu na alteração de configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     * @param int $coFrenteAnterior
     */
    private function excluirFrente(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario, $coFrenteAnterior)
    {
        if ($configuracaoQuestionarioTO->co_frente != $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_CURSO) {
            $this->getConfiguracaoQuestionarioFrenteCursoBO()->excluirPorConfiguracoQuestionario($coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente != $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_TURMA) {
            $this->getConfiguracaoQuestionarioFrenteTurmaBO()->excluirPorConfiguracoQuestionario($coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente != $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_UNIDADE_ENSINO) {
            $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()->excluirPorConfiguracoQuestionario($coConfiguracaoQuestionario);
            $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()->excluirPorConfiguracoQuestionario($coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente != $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_EIXO_TECNOLOGICO) {
            $this->getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()->excluirPorConfiguracoQuestionario($coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_CURSO) {
            $this->excluirFrenteCurso($configuracaoQuestionarioTO, $coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_TURMA) {
            $this->excluirFrenteTurma($configuracaoQuestionarioTO, $configuracaoQuestionarioTO->co_tipo_iniciativa, $coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_UNIDADE_ENSINO) {
            $this->excluirFrenteUnidadeEnsino($configuracaoQuestionarioTO, $coConfiguracaoQuestionario);
            $this->excluirFrenteUnidadeEnsinoRemota($configuracaoQuestionarioTO, $coConfiguracaoQuestionario);
        }

        if ($configuracaoQuestionarioTO->co_frente == $coFrenteAnterior && $coFrenteAnterior == static::FRENTE_QUESTIONARIO_EIXO_TECNOLOGICO) {
            $this->excluirFrenteEixoTecnologico($configuracaoQuestionarioTO, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover as frentes de curso que o usuário removeu na alteração de configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirFrenteCurso(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $coFrenteCursos = array();
        $coFrenteCursosRemovidos = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->dados_frente_nao_selecionados;
        $configuracaoFrenteCursos = $this->getConfiguracaoQuestionarioFrenteCursoBO()->getConfiguracaoQuestionarioFrenteCursoPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoFrenteCursos as $confFrenteCurso) {
            $coFrenteCursos[] = $confFrenteCurso->co_curso;
        }

        if (!empty($coFrenteCursos) && !empty($listaItensNaoSelecionados)) {
            $coFrenteCursosRemovidos = array_intersect($coFrenteCursos, $listaItensNaoSelecionados);
        }

        if (!empty($coFrenteCursosRemovidos)) {
            $this->getConfiguracaoQuestionarioFrenteCursoBO()->excluirEmLotePorCursosConfiguracaoQuestionario($coFrenteCursosRemovidos, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover as frentes de turma que o usuário removeu na alteração de configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirFrenteTurma(stdClass $configuracaoQuestionarioTO, $coIniciativa, $coConfiguracaoQuestionario)
    {
        $coFrenteTurma = array();
        $coFrenteTurmaRemovidos = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->dados_frente_nao_selecionados;
        $configuracaoFrenteTurma = $this->getConfiguracaoQuestionarioFrenteTurmaBO()->getConfiguracaoQuestionarioFrenteTurmaPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoFrenteTurma as $confFrenteTurma) {
            $coFrenteTurma[] = $confFrenteTurma->co_turma;
        }

        if (!empty($coFrenteTurma) && !empty($listaItensNaoSelecionados)) {
            $coFrenteTurmaRemovidos = array_intersect($coFrenteTurma, $listaItensNaoSelecionados);
        }

        if (!empty($coFrenteTurmaRemovidos)) {
            $this->getConfiguracaoQuestionarioFrenteTurmaBO()->excluirEmLotePorTurmaConfiguracaoQuestionario($coFrenteTurmaRemovidos, $coIniciativa, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover as frentes de unidade de ensino que o usuário removeu na alteração de configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirFrenteUnidadeEnsino(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $coFrenteUnidadeEnsinos = array();
        $coUnidadeEnsinosRemovidos = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->dados_frente_nao_selecionados;
        $configuracaoFrenteUnidadesEnsino = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()->getConfiguracaoQuestionarioFrenteUnidadeEnsinoPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoFrenteUnidadesEnsino as $confFrenteUnidadeEnsino) {
            $coFrenteUnidadeEnsinos[] = $confFrenteUnidadeEnsino->co_unidade_ensino;
        }

        if (!empty($coFrenteUnidadeEnsinos) && !empty($listaItensNaoSelecionados)) {
            $coUnidadeEnsinosRemovidos = array_intersect($coFrenteUnidadeEnsinos, $listaItensNaoSelecionados);
        }

        if (!empty($coUnidadeEnsinosRemovidos)) {
            $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()->excluirEmLotePorUnidadeEnsinoConfiguracaoQuestionario($coUnidadeEnsinosRemovidos, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover as frentes de unidade de ensino remota que o usuário removeu na alteração de configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirFrenteUnidadeEnsinoRemota(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $coFrenteUnidadeEnsinoRemotas = array();
        $coUnidadeEnsinoRemotasRemovidos = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->uer_nao_selecionadas;
        $configuracaoFrenteUnidadeEnsinoRemotas = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()->getPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoFrenteUnidadeEnsinoRemotas as $confFrenteUnidadeEnsinoRemota) {
            $coFrenteUnidadeEnsinoRemotas[] = $confFrenteUnidadeEnsinoRemota->co_unidade_ensino_remota;
        }

        if (!empty($coFrenteUnidadeEnsinoRemotas) && !empty($listaItensNaoSelecionados)) {
            $coUnidadeEnsinoRemotasRemovidos = array_intersect($coFrenteUnidadeEnsinoRemotas, $listaItensNaoSelecionados);
        }

        if (!empty($coUnidadeEnsinoRemotasRemovidos)) {
            $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()->excluirEmLotePorUnidadeEnsinoRemotaConfiguracaoQuestionario($coUnidadeEnsinoRemotasRemovidos, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover os eixos tecnológicos que o usuário removeu na alteração de configuração de questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param int $coConfiguracaoQuestionario
     */
    private function excluirFrenteEixoTecnologico(stdClass $configuracaoQuestionarioTO, $coConfiguracaoQuestionario)
    {
        $coFrenteEixoTecnologico = array();
        $coEixoTecnologicoRemovidos = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->dados_frente_nao_selecionados;
        $configuracaoFrenteEixoTecnologico = $this->getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()->getConfiguracaoQuestionarioFrenteEixoTecnologicoPorConfiguracaoQuestionario($coConfiguracaoQuestionario);

        foreach ($configuracaoFrenteEixoTecnologico as $confFrenteEixoTecnologico) {
            $coFrenteEixoTecnologico[] = $confFrenteEixoTecnologico->co_eixo_tecnologico;
        }

        if (!empty($coFrenteEixoTecnologico) && !empty($listaItensNaoSelecionados)) {
            $coEixoTecnologicoRemovidos = array_intersect($coFrenteEixoTecnologico, $listaItensNaoSelecionados);
        }

        if (!empty($coEixoTecnologicoRemovidos)) {
            $this->getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()->excluirEmLotePorEixoConfiguracaoQuestionario($coEixoTecnologicoRemovidos, $coConfiguracaoQuestionario);
        }
    }

    /**
     * Responsável por remover as esferas administrativas que foram removidas na configuração do questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param integer $coConfQuestionarioPactuacao
     */
    private function excluirEsferaAdministrativa(stdClass $configuracaoQuestionarioTO, $coConfQuestionarioPactuacao)
    {
        $coEsferasAdministrativas = array();
        $coEsferaAdministrativaRemovidas = array();
        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->esferas_administrativas_nao_selecionadas;
        $confEsferasAdministrativas = $this->getConfiguracaoQuestionarioEsferaAdministrativaBO()->getEsferasPorConfiguracaoPactuacao($coConfQuestionarioPactuacao);

        foreach ($confEsferasAdministrativas as $confEsferaAdministrativa) {
            $coEsferasAdministrativas[] = $confEsferaAdministrativa->co_dependencia_admin;
        }

        if (!empty($coEsferasAdministrativas) && !empty($listaItensNaoSelecionados)) {
            $coEsferaAdministrativaRemovidas = array_intersect($coEsferasAdministrativas, $listaItensNaoSelecionados);
        }

        if (!empty($coEsferaAdministrativaRemovidas)) {
            $this->getConfiguracaoQuestionarioEsferaAdministrativaBO()->excluirEmLotePorEsferasConfiguracaoPactuacao($coEsferaAdministrativaRemovidas, $coConfQuestionarioPactuacao);
        }
    }

    /**
     * Responsável por remover as sub esferas administrativas que foram removidas na configuração do questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @param integer $coConfQuestionarioPactuacao
     */
    private function excluirSubEsferaAdmistrativa(stdClass $configuracaoQuestionarioTO, $coConfQuestionarioPactuacao)
    {
        $coSubEsferaAdministrativa = array();
        $coSubEsferasAdministrativasRemovidas = array();

        $listaItensNaoSelecionados = $configuracaoQuestionarioTO->sub_esferas_administrativas_nao_selecionadas;
        $confSubEsferasAdministrativas = $this->getConfiguracaoQuestionarioSubEsferaAdministrativaBO()->getSubEsferaPorConfiguracaoPactuacao($coConfQuestionarioPactuacao);

        foreach ($confSubEsferasAdministrativas as $confSubEsferaAdministrativa) {
            $coSubEsferaAdministrativa[] = $confSubEsferaAdministrativa->co_subdependencia_admin;
        }

        if (!empty($coSubEsferaAdministrativa) && !empty($listaItensNaoSelecionados)) {
            $coSubEsferasAdministrativasRemovidas = array_intersect($coSubEsferaAdministrativa,
                $listaItensNaoSelecionados);
        }

        if (!empty($coSubEsferasAdministrativasRemovidas)) {
            $this->getConfiguracaoQuestionarioSubEsferaAdministrativaBO()->excluirEmLotePorSubEsferasConfiguracaoPactuacao($coSubEsferasAdministrativasRemovidas, $coConfQuestionarioPactuacao);
        }
    }

    /**
     * Valida o preenchimento dos campos obrigatórios para a configuração do questionário.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @throws Aviso
     */
    private function validarCamposObrigatorios(stdClass $configuracaoQuestionarioTO)
    {
        $erros = false;

        if (empty($configuracaoQuestionarioTO->co_tipo_iniciativa)) {
            $erros = true;
        }

        if (empty($configuracaoQuestionarioTO->co_questionario)) {
            $erros = true;
        }

        if (empty($configuracaoQuestionarioTO->co_modalidade)) {
            $erros = true;
        }

        if (empty($configuracaoQuestionarioTO->co_tipo_curso)) {
            $erros = true;
        }

        if ($configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_MEDIOTEC || $configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_SISUTEC) {
            if (empty($configuracaoQuestionarioTO->co_adesao_sisu_tecnico_edital)) {
                $erros = true;
            }
        }

        if ($configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_PRONATEC_VOLUNTARIO) {
            if (empty($configuracaoQuestionarioTO->co_adesao_edital_lote)) {
                $erros = true;
            }
        }

        if ($configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_PACTUACAO) {
            if (empty($configuracaoQuestionarioTO->co_pactuacao_periodo)) {
                $erros = true;
            }

            if (empty($configuracaoQuestionarioTO->nu_ano_pactua_periodo)) {
                $erros = true;
            }

            if ($configuracaoQuestionarioTO->co_tipo_curso == Sistec_Constantes::CO_TIPO_CURSO_TECNICO && empty($configuracaoQuestionarioTO->co_tipo_curso_tecnico)) {
                $erros = true;
            }
        }

        if (!empty($configuracaoQuestionarioTO->st_informativo_vinculado) && empty($configuracaoQuestionarioTO->st_informativo_disponivel_periodo_questionario)) {
            if (empty($configuracaoQuestionarioTO->dt_inicio_informativo)) {
                $erros = true;
            }

            if (empty($configuracaoQuestionarioTO->dt_termino_informativo)) {
                $erros = true;
            }
        }

        if ($configuracaoQuestionarioTO->st_informativo_vinculado && $configuracaoQuestionarioTO->st_enviar_email_informativo) {
            if (empty($configuracaoQuestionarioTO->dt_envio_email_informativo)) {
                $erros = true;
            }
        }

        if (!empty($configuracaoQuestionarioTO->co_frente) && empty($configuracaoQuestionarioTO->dados_frente) && empty($configuracaoQuestionarioTO->dados_frente_cadastradas)) {
            $erros = true;
        }

        if (!empty($configuracaoQuestionarioTO->co_configuracao_questionario) && empty($configuracaoQuestionarioTO->ds_justificativa_alteracao)) {
            $erros = true;
        }

        if ($erros) {
            throw new Aviso('O(s) campo(s) marcados com "*" são de preenchimento obrigatório!');
        }
    }

    /**
     * Valida se os campos obrigatórios para redisponibilização de questionário foram informados.
     *
     * @param stdClass $redisponibilizacao
     * @throws Aviso
     */
    public function validarCamposObrigatoriosRedisponibilizacao(stdClass $redisponibilizacao)
    {
        if (empty($redisponibilizacao->redisponibilidades)) {
            $erros = true;
        }

        if (!isset($redisponibilizacao->stInformativoVinculado) || $redisponibilizacao->stInformativoVinculado == '') {
            $erros = true;
        }

        if (boolval($redisponibilizacao->stInformativoVinculado) && (!isset($redisponibilizacao->stEnviaEmailInformativo) || $redisponibilizacao->stEnviaEmailInformativo == '')) {
            $erros = true;
        }

        if (boolval($redisponibilizacao->stInformativoVinculado) && !boolval($redisponibilizacao->stInformativoDisponivelPeriodoQuestionario)) {
            if (empty($redisponibilizacao->dtInicioInformativo)) {
                $erros = true;
            }

            if (empty($redisponibilizacao->dtTerminoInformativo)) {
                $erros = true;
            }
        }

        if (boolval($redisponibilizacao->stInformativoVinculado) && boolval($redisponibilizacao->stEnviaEmailInformativo)) {
            if (empty($redisponibilizacao->dtEnvioEmailInformativo)) {
                $erros = true;
            }
        }

        if ($erros) {
            throw new Aviso('O(s) campo(s) marcados com "*" são de preenchimento obrigatório!');
        }
    }

    /**
     * Valida os campos de data presentes informados.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @throws AViso
     */
    private function validarDatas(stdClass $configuracaoQuestionarioTO)
    {
        $erros = false;

        if ($configuracaoQuestionarioTO->st_informativo_vinculado && !$configuracaoQuestionarioTO->st_informativo_disponivel_periodo_questionario) {
            if (!$this->validaData($configuracaoQuestionarioTO->dt_inicio_informativo)) {
                $erros = true;
            }

            if (!$this->validaData($configuracaoQuestionarioTO->dt_termino_informativo)) {
                $erros = true;
            }
        }

        if ($configuracaoQuestionarioTO->st_informativo_vinculado && $configuracaoQuestionarioTO->st_envia_email_informativo) {
            if (!$this->validaData($configuracaoQuestionarioTO->dt_envio_email_informativo)) {
                $erros = true;
            }
        }

        if ($erros) {
            throw new Aviso('O(s) campo(s) de data informado são inválidos!');
        }
    }

    /**
     * Valida a se a data do informativo do questionário informada é válida.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @throws Aviso
     */
    private function validarDataInformativoQuestionario(stdClass $configuracaoQuestionarioTO)
    {
        $dataAtual = new Zend_Date();
        $dataInicioInformativo = new Zend_Date($configuracaoQuestionarioTO->dt_inicio_informativo);
        $dataTerminoInformativo = new Zend_Date($configuracaoQuestionarioTO->dt_termino_informativo);

        if ($dataInicioInformativo->isEarlier($dataAtual, 'dd/MM/YYYY')) {
            throw new Aviso('A data do Início do Informativo deve ser igual ou posterior a data corrente!');
        }

        if ($dataTerminoInformativo->isEarlier($dataInicioInformativo, 'dd/MM/YYYY')) {
            throw new Aviso('Data de término do informativo não pode ser anterior a data de início do informativo.');
        }
    }

    /**
     * Valida a se a data do informativo da redisponibilidade informada é válida.
     *
     * @param stdClass $redisponibilizacao
     * @throws Aviso
     * @throws Zend_Date_Exception
     */
    private function validarDataInformativoRedisponibilidade(stdClass $redisponibilizacao)
    {
        $dataAtual = new Zend_Date();
        $dataInicioInformativo = new Zend_Date($redisponibilizacao->dtInicioInformativo);
        $dataTerminoInformativo = new Zend_Date($redisponibilizacao->dtTerminoInformativo);

        if ($dataInicioInformativo->isEarlier($dataAtual, 'dd/MM/YYYY')) {
            throw new Aviso('A data do Início do Informativo deve ser igual ou posterior a data corrente');
        }

        if ($dataTerminoInformativo->isEarlier($dataInicioInformativo, 'dd/MM/YYYY')) {
            throw new Aviso('A data de inicio do informativo deve ser anterior a data de término');
        }
    }

    /**
     * Valida se a configuração do questionário está (Disponível para resposta, Programado, Finalizado) e realiza validações.
     *
     * @param integer $coConfiguracaoQuestionario
     * @param boolean $confirmacaoExclusao
     * @param string $baseUrl
     * @throws Aviso
     */
    private function validarSituacaoConfiguracaoQuestionario($coConfiguracaoQuestionario, $confirmacaoExclusao, $baseUrl)
    {
        $configuracaoQuestionario = $this->getConfiguracaoQuestionarioDAO()->getConfiguracaoQuestionario($coConfiguracaoQuestionario);

        if ($configuracaoQuestionario->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && $configuracaoQuestionario->st_ativo != false) {
            throw new Aviso("Não é possível excluir esse questionário, ele está disponibilizado para respostas. Em caso de emergência, inative esse questionário no ícone <img src='{$baseUrl}/publico/img/botoes/load.png'",
                400);
        }

        if ($configuracaoQuestionario->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO && !$confirmacaoExclusao) {
            throw new Aviso("Ao excluir a configuração para esse questionário, ele não será disponibilizado para o público alvo. Deseja prosseguir?",
                415);
        }

        if ($configuracaoQuestionario->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO && !$confirmacaoExclusao) {
            throw new Aviso("Questionário Finalizado! Não é possível realizar a Exclusão!", 400);
        }

        $pessoaRespostaQuestionario = $this->getConfiguracaoQuestionarioDAO()->getRespostasPessoaConfiguracao($coConfiguracaoQuestionario);
        if ($pessoaRespostaQuestionario > 0) {
            throw new Aviso("Não é possível realizar a exclusão desse questionário, há respostas vinculadas a ele.",
                400);
        }

        $redisponibilidades = $this->getConfiguracaoQuestionarioDAO()->getCountRedisponibilizacoes($coConfiguracaoQuestionario);
        if ($redisponibilidades > 0) {
            throw new Aviso("Não é possível realizar a exclusão desse questionário, há redisponibilizações vinculadas a ele! Caso necessário, exclua as redisponibilizações para prosseguir com a exclusão do questionário.",
                400);
        }

    }

    /**
     * Valida em que status a redisponibilidade da configuração do questionário e realiza validações.
     *
     * @param integer $coRedisponibilidadeConfiguracao
     * @throws Aviso
     */
    private function validarSituacaoRedisponibilidade($coRedisponibilidadeConfiguracao)
    {
        $redisponibilidade = $this->getConfiguracaoQuestionarioDAO()->getConfiguracaoQuestionario($coRedisponibilidadeConfiguracao);

        if ($redisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && !empty($redisponibilidade->st_ativo)) {
            throw new Erro('Não é possível excluir uma redisponibilização vigente. Em caso de emergência, inative o questionário e realize a exclusão!');
        }

        if ($redisponibilidade->tp_situacao === self::TP_SITUACAO_DISPONIVEL_FINALIZADO) {
            throw new Erro('Não é possível excluir uma redisponibilização Finalizada.');
        }

        $this->isVinculoQuestionarioRespostas($coRedisponibilidadeConfiguracao);
    }

    /**
     * Valida se a data para envio de email do informativo está no periodo de vigência das turmas do edital.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @throws Aviso
     */
    private function validarPeriodoEnvioEmailInformativo(stdClass $configuracaoQuestionarioTO)
    {
        $disponibilidade = null;
        $dtInformativo = Utils::getDataToString($configuracaoQuestionarioTO->dt_envio_email_informativo,
            Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
        $dtInformativo = Utils::getDataHoraZero($dtInformativo);

        $turmas = $configuracaoQuestionarioTO->co_frente == static::FRENTE_QUESTIONARIO_TURMA ? $configuracaoQuestionarioTO->dados_frente : array();

        // Caso 'Disponibilidade do Questionário'
        if ($configuracaoQuestionarioTO->st_informativo_disponivel_periodo_questionario) {

            // Caso Iniciativa Pactuação
            if ($configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_PACTUACAO) {
                $disponibilidade = $this->getConfiguracaoQuestionarioPactuacaoBO()->getDataMinimaMaximaPeriodoDisponibilidade($configuracaoQuestionarioTO->co_pactuacao_periodo,
                    $turmas);
                $disponibilidade->dt_minima = Utils::getDataToString($disponibilidade->dt_minima,
                    Utils::FORMATO_DATA_BD, Utils::TIMEZONE_PADRAO);
                $disponibilidade->dt_maxima = Utils::getDataToString($disponibilidade->dt_maxima,
                    Utils::FORMATO_DATA_BD, Utils::TIMEZONE_PADRAO);
                // Caso Inicitava Mediotec, Sisutec e Pronatec Voluntário.
            } else {
                $coBuscarDisponibilidades = $configuracaoQuestionarioTO->co_tipo_iniciativa == static::TP_INICIATIVA_PRONATEC_VOLUNTARIO ? $configuracaoQuestionarioTO->co_adesao_edital_lote : $configuracaoQuestionarioTO->co_adesao_sisu_tecnico_edital;
                $disponibilidade = $this->getDataMinimaMaximaPeriodoDisponibilidade($configuracaoQuestionarioTO->co_tipo_iniciativa,
                    $coBuscarDisponibilidades, $turmas);
                $disponibilidade->dt_minima = Utils::getDataToString($disponibilidade->dt_minima,
                    Utils::FORMATO_TIMESTAMP_BD, Utils::TIMEZONE_PADRAO);
                $disponibilidade->dt_maxima = Utils::getDataToString($disponibilidade->dt_maxima,
                    Utils::FORMATO_TIMESTAMP_BD, Utils::TIMEZONE_PADRAO);
            }

            $disponibilidade->dt_minima->modify('first day of this month');
            $disponibilidade->dt_maxima->modify('last day of this month');

            // Caso 'Período do Informativo'.
        } else {
            $disponibilidade = new stdClass();

            $disponibilidade->dt_minima = Utils::getDataToString($configuracaoQuestionarioTO->dt_inicio_informativo,
                Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $disponibilidade->dt_minima = Utils::getDataHoraZero($disponibilidade->dt_minima);

            $disponibilidade->dt_maxima = Utils::getDataToString($configuracaoQuestionarioTO->dt_termino_informativo,
                Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $disponibilidade->dt_maxima = Utils::getDataHoraZero($disponibilidade->dt_maxima);
        }

        if ($dtInformativo < $disponibilidade->dt_minima || $dtInformativo > $disponibilidade->dt_maxima) {
            throw new Aviso('A data do envio do informativo deve estar dentro dos meses de disponibilidade do questionário ou entre as datas de inicio e fim do informativo');
        }
    }

    /**
     * Valida se a data de envio de email de informativo compreende um periodo válido.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @throws Aviso
     */
    private function validarDataEnvioEmailInformativo(stdClass $configuracaoQuestionarioTO)
    {
        $dataAtual = new Zend_Date();
        $dataEnvioEmail = new Zend_Date($configuracaoQuestionarioTO->dt_envio_email_informativo);

        if ($dataEnvioEmail->isEarlier($dataAtual, 'dd/MM/YYYY')) {
            throw new Aviso('A data do envio do informativo por e-mail deve ser igual ou posterior a data corrente!');
        }
    }

    /**
     * Valida se o período do informativo é válido.
     *
     * @param date $dtInicioInformativo
     * @param date $dtTerminoInformativo
     * @param integer $iniciativa
     * @param integer $coBuscarDisponibilidades
     * @param array $turmas
     * @throws Aviso
     */
    private function validarPeriodoInformativo($dtInicioInformativo, $dtTerminoInformativo, $iniciativa, $coBuscarDisponibilidades, $turmas)
    {
        $dtInicioInformativo = new Zend_Date($dtInicioInformativo);
        $dtTerminoInformativo = new Zend_Date($dtTerminoInformativo);

        if ($iniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO) {
            $dtMaximaMinimaPeriodoDisponibilidade = $this->getConfiguracaoQuestionarioPactuacaoBO()->getDataMinimaMaximaPeriodoDisponibilidade($coBuscarDisponibilidades,
                $turmas);
        } else {
            $dtMaximaMinimaPeriodoDisponibilidade = $this->getDataMinimaMaximaPeriodoDisponibilidade($iniciativa,
                $coBuscarDisponibilidades, $turmas);
        }

        $dtMinimaDisponibilidade = new Zend_Date($dtMaximaMinimaPeriodoDisponibilidade->dt_minima);
        $dtMaximaDisponibilidade = new Zend_Date($dtMaximaMinimaPeriodoDisponibilidade->dt_maxima);

        $dtMinimaDisponibilidade->setDay(1);
        $dtMaximaDisponibilidade->setDay($dtMaximaDisponibilidade->get(Zend_Date::MONTH_DAYS));

        if ($dtInicioInformativo->isEarlier($dtMinimaDisponibilidade) || $dtTerminoInformativo->isLater($dtMaximaDisponibilidade)) {
            $msg = "A data informada está fora do período de disponibilidade de turmas do Edital";

            if ($iniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PRONATEC_VOLUNTARIO) {
                $msg = 'A data informada está fora do período de disponibilidade de turmas do lote';
            }

            if ($iniciativa == ConfiguracaoQuestionarioBO::TP_INICIATIVA_PACTUACAO) {
                $msg = 'A data informada está fora do período de disponibilidade de turmas do Ano da pactuação';
            }

            throw new Aviso($msg);
        }
    }

    /**
     * Válida as datas referente ao período da redisponibilidade selecionados.
     *
     * @param stdClass $redisponibilidadeTO
     * @throws Aviso
     */
    private function validarDatasPeriodoRedisponibilidade(stdClass $redisponibilidadeTO)
    {
        $periodoDisponibilidadeSelecionadas = $this->getPeriodoMesAnoDisponibilidadeSelecionadas($redisponibilidadeTO->redisponibilidades);

        $menorDisponibilidade = str_pad($periodoDisponibilidadeSelecionadas->menorDisponibilidade, 6, '0', STR_PAD_LEFT);
        $menorDisponibilidade = substr($menorDisponibilidade, 2, 4) . substr($menorDisponibilidade, 0, 2);

        $maiorDisponibilidade = str_pad($periodoDisponibilidadeSelecionadas->maiorDisponibilidade, 6, '0', STR_PAD_LEFT);
        $maiorDisponibilidade = substr($maiorDisponibilidade, 2, 4) . substr($maiorDisponibilidade, 0, 2);

        if (!empty($redisponibilidadeTO->dtInicioInformativo) && !empty($redisponibilidadeTO->dtTerminoInformativo)) {
            $dataInicioInformativo = Utils::getDataToString($redisponibilidadeTO->dtInicioInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $dataInicioInformativo = $dataInicioInformativo->format('Ym');

            $dataTerminoInformativo = Utils::getDataToString($redisponibilidadeTO->dtTerminoInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $dataTerminoInformativo = $dataTerminoInformativo->format('Ym');

            if ($dataInicioInformativo < $menorDisponibilidade || $dataTerminoInformativo > $maiorDisponibilidade) {
                throw new Aviso('A data do informativo deve estar dentro do período de redisponibilidade do questinário.');
            }
        }

        if (boolval($redisponibilidadeTO->stInformativoDisponivelPeriodoQuestionario) && !empty($redisponibilidadeTO->dtEnvioEmailInformativo)) {
            $dataEnvioEmail = Utils::getDataToString($redisponibilidadeTO->dtEnvioEmailInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $dataEnvioEmail = $dataEnvioEmail->format('Ym');

            if ($menorDisponibilidade > $dataEnvioEmail || $maiorDisponibilidade < $dataEnvioEmail) {
                throw new Aviso('A data do envio do informativo por e-mail deve estar dentro do período de redisponibilidade do questinário');
            }
        }
    }

    /**
     * Realiza as validações referente a data de envio de email de informativo para as redisponibilidades.
     *
     * @param stdClass $redisponibilidadeTO
     * @throws Aviso
     */
    private function validarDataEnvioEmailInformativoRedisponibilidade(stdClass $redisponibilidadeTO)
    {
        $dataAtual = Utils::getData();
        $dataEnvioEmail = Utils::getDataToString($redisponibilidadeTO->dtEnvioEmailInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);

        if ($dataEnvioEmail < $dataAtual) {
            throw new Aviso('A data do envio do informativo por e-mail deve ser igual ou posterior a data corrente');
        }

        if (!boolval($redisponibilidadeTO->stInformativoDisponivelPeriodoQuestionario)) {
            $dataInicioInfo = Utils::getDataToString($redisponibilidadeTO->dtInicioInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $dataTerminoInfo = Utils::getDataToString($redisponibilidadeTO->dtTerminoInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);

            if ($dataInicioInfo > $dataEnvioEmail || $dataTerminoInfo < $dataEnvioEmail) {
                throw new Aviso('A data do envio do informativo por e-mail deve estar dentro do período de redisponibilidade do questinário');
            }
        }
    }

    /**
     * Recupera uma nova instância de configuração questionário para realizar a inserção dos dados.
     *
     * @param stdClass $configuracaoQuestionarioTO
     * @return object
     */
    private function getNovaConfiguracaoQuestionario(stdClass $configuracaoQuestionarioTO)
    {
        $dataAtual = new Zend_Date();
        $configuracaoQuestionario = new stdClass();

        $configuracaoQuestionario->dt_inicio_informativo = null;
        $configuracaoQuestionario->dt_termino_informativo = null;
        $configuracaoQuestionario->dt_envio_email_informativo = null;
        $configuracaoQuestionario->co_tipo_curso = $configuracaoQuestionarioTO->co_tipo_curso;
        $configuracaoQuestionario->co_modalidade = $configuracaoQuestionarioTO->co_modalidade;
        $configuracaoQuestionario->st_ativo = ($configuracaoQuestionarioTO->st_ativo) ? 't' : 'f';
        $configuracaoQuestionario->co_questionario = $configuracaoQuestionarioTO->co_questionario;
        $configuracaoQuestionario->tp_publico_alvo = $configuracaoQuestionarioTO->tp_publico_alvo;
        $configuracaoQuestionario->co_tipo_iniciativa = $configuracaoQuestionarioTO->co_tipo_iniciativa;
        $configuracaoQuestionario->co_pessoa_inst_grupo = $configuracaoQuestionarioTO->co_pessoa_inst_grupo;
        $configuracaoQuestionario->co_pessoa_inst_grupo = $configuracaoQuestionarioTO->co_pessoa_inst_grupo;
        $configuracaoQuestionario->co_adesao_edital_lote = $configuracaoQuestionarioTO->co_adesao_edital_lote;
        $configuracaoQuestionario->st_macrorregiao = ($configuracaoQuestionarioTO->st_macrorregiao) ? 't' : 'f';
        $configuracaoQuestionario->co_configuracao_questionario = $configuracaoQuestionarioTO->co_configuracao_questionario;
        $configuracaoQuestionario->co_adesao_sisu_tecnico_edital = $configuracaoQuestionarioTO->co_adesao_sisu_tecnico_edital;
        $configuracaoQuestionario->st_informativo_vinculado = ($configuracaoQuestionarioTO->st_informativo_vinculado) ? 't' : 'f';
        $configuracaoQuestionario->st_envia_email_informativo = ($configuracaoQuestionarioTO->st_envia_email_informativo) ? 't' : 'f';
        $configuracaoQuestionario->st_questionario_obrigatorio = ($configuracaoQuestionarioTO->st_questionario_obrigatorio) ? 't' : 'f';
        $configuracaoQuestionario->tp_frente = (!empty($configuracaoQuestionarioTO->co_frente)) ? $configuracaoQuestionarioTO->co_frente : null;
        $configuracaoQuestionario->co_info = (!empty($configuracaoQuestionarioTO->co_informativo)) ? $configuracaoQuestionarioTO->co_informativo : null;
        $configuracaoQuestionario->st_informativo_disponivel_periodo_questionario = ($configuracaoQuestionarioTO->st_informativo_disponivel_periodo_questionario) ? 't' : 'f';

        if (!empty($configuracaoQuestionarioTO->dt_inicio_informativo)) {
            $configuracaoQuestionario->dt_inicio_informativo = Utils::getDataToString($configuracaoQuestionarioTO->dt_inicio_informativo,
                Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $configuracaoQuestionario->dt_inicio_informativo = $configuracaoQuestionario->dt_inicio_informativo->format(Utils::FORMATO_TIMESTAMP_BD);
        }

        if (!empty($configuracaoQuestionarioTO->dt_termino_informativo)) {
            $configuracaoQuestionario->dt_termino_informativo = Utils::getDataToString($configuracaoQuestionarioTO->dt_termino_informativo,
                Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $configuracaoQuestionario->dt_termino_informativo = $configuracaoQuestionario->dt_termino_informativo->format(Utils::FORMATO_TIMESTAMP_BD);
        }

        if (!empty($configuracaoQuestionarioTO->dt_envio_email_informativo)) {
            $configuracaoQuestionario->dt_envio_email_informativo = Utils::getDataToString($configuracaoQuestionarioTO->dt_envio_email_informativo,
                Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $configuracaoQuestionario->dt_envio_email_informativo = $configuracaoQuestionario->dt_envio_email_informativo->format(Utils::FORMATO_DATA_BD);
        }

        if (empty($configuracaoQuestionarioTO->co_configuracao_questionario)) {
            $configuracaoQuestionario->tp_situacao = static::TP_SITUACAO_DISPONIVEL_PROGRAMADO;
        } else {
            $configuracaoQuestionario->tp_situacao = $this->getConfiguracaoQuestionarioDAO()->getSituacao($configuracaoQuestionarioTO->co_configuracao_questionario);
        }

        return $configuracaoQuestionario;
    }

    /**
     * Recupera os dados da nova redisponibilidade de acordo com os dados informados.
     *
     * @param stdClass $redisponibilidadeTO
     * @return stdClass
     * @throws Erro
     */
    private function getNovaRedisponibilidade(stdClass $redisponibilidadeTO)
    {
        $configuracaoQuestionario = $this->getConfiguracaoQuestionario($redisponibilidadeTO->coConfQuestionarioPai);

        $redisponibilidade = new stdClass();
        $redisponibilidade->st_macrorregiao = (!empty($configuracaoQuestionario->st_macrorregiao)) ? $configuracaoQuestionario->st_macrorregiao : 'f';
        $redisponibilidade->dt_inicio_informativo = $configuracaoQuestionario->dt_inicio_informativo;
        $redisponibilidade->dt_termino_informativo = $configuracaoQuestionario->dt_termino_informativo;
        $redisponibilidade->dt_envio_email_informativo = $configuracaoQuestionario->dt_envio_email_informativo;
        $redisponibilidade->tp_frente = $configuracaoQuestionario->tp_frente;
        $redisponibilidade->tp_situacao = static::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA;
        $redisponibilidade->co_pessoa_inst_grupo = $redisponibilidadeTO->coPessoaInstGrupo;
        $redisponibilidade->co_conf_questionario_pai = $redisponibilidadeTO->coConfQuestionarioPai;
        $redisponibilidade->co_configuracao_questionario = $redisponibilidadeTO->coRedisponibilizacao;
        $redisponibilidade->st_informativo_vinculado = ($redisponibilidadeTO->stInformativoVinculado) ? 't' : 'f';
        $redisponibilidade->co_info = (!empty($redisponibilidadeTO->coInfo)) ? $redisponibilidadeTO->coInfo : null;
        $redisponibilidade->st_envia_email_informativo = ($redisponibilidadeTO->stEnviaEmailInformativo) ? 't' : 'f';
        $redisponibilidade->st_informativo_disponivel_periodo_questionario = ($redisponibilidadeTO->stInformativoDisponivelPeriodoQuestionario) ? 't' : 'f';

        $redisponibilidade->co_tipo_curso = $configuracaoQuestionario->co_tipo_curso;
        $redisponibilidade->co_modalidade = $configuracaoQuestionario->co_modalidade;
        $redisponibilidade->co_questionario = $configuracaoQuestionario->co_questionario;
        $redisponibilidade->tp_publico_alvo = $configuracaoQuestionario->tp_publico_alvo;
        $redisponibilidade->st_ativo = ($configuracaoQuestionario->st_ativo) ? 't' : 'f';
        $redisponibilidade->co_tipo_iniciativa = $configuracaoQuestionario->co_tipo_iniciativa;
        $redisponibilidade->co_adesao_edital_lote = $configuracaoQuestionario->co_adesao_edital_lote;
        $redisponibilidade->co_adesao_sisu_tecnico_edital = $configuracaoQuestionario->co_adesao_sisu_tecnico_edital;
        $redisponibilidade->st_questionario_obrigatorio = ($configuracaoQuestionario->st_questionario_obrigatorio) ? 't' : 'f';

        if (!empty($redisponibilidadeTO->dtInicioInformativo)) {
            $redisponibilidade->dt_inicio_informativo = Utils::getDataToString($redisponibilidadeTO->dtInicioInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $redisponibilidade->dt_inicio_informativo = $redisponibilidade->dt_inicio_informativo->format(Utils::FORMATO_TIMESTAMP_BD);
        }

        if (!empty($redisponibilidadeTO->dtTerminoInformativo)) {
            $redisponibilidade->dt_termino_informativo = Utils::getDataToString($redisponibilidadeTO->dtTerminoInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $redisponibilidade->dt_termino_informativo = $redisponibilidade->dt_termino_informativo->format(Utils::FORMATO_TIMESTAMP_BD);
        }

        if (!empty($redisponibilidadeTO->dtEnvioEmailInformativo)) {
            $redisponibilidade->dt_envio_email_informativo = Utils::getDataToString($redisponibilidadeTO->dtEnvioEmailInformativo, Utils::FORMATO_DATA_VIEW, Utils::TIMEZONE_PADRAO);
            $redisponibilidade->dt_envio_email_informativo = $redisponibilidade->dt_envio_email_informativo->format(Utils::FORMATO_DATA_BD);
        }

        if (!empty($redisponibilidadeTO->coRedisponibilizacao)) {
            $redisponibilidade->tp_situacao = $this->getConfiguracaoQuestionarioDAO()->getSituacao($redisponibilidadeTO->coRedisponibilizacao);
        }

        return $redisponibilidade;
    }

    /**
     * Altera a situação da configuração informada conforme os critérios especificados.
     *
     * @param $configuracaoQuestionario
     * @throws Erro
     */
    private function atualizarStatusDisponibilidade($configuracaoQuestionario)
    {
        $mesAtual = (new Zend_Date())->get('YYYY-MM-01');
        $disponibilidadeQuestionario = $this->getConfiguracaoQuestionarioDisponibilidadeBO()->getNuMesAnoPorConfQuestionarioArray($configuracaoQuestionario->co_configuracao_questionario);
        $nuMesAnoDisponibilidades = array_column($disponibilidadeQuestionario, 'nu_ano_mes_dia');

        if ($configuracaoQuestionario->tp_situacao == self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && end($nuMesAnoDisponibilidades) < $mesAtual) {
            $novaConfiguracao['co_configuracao_questionario'] = $configuracaoQuestionario->co_configuracao_questionario;
            $novaConfiguracao['tp_situacao'] = self::TP_SITUACAO_DISPONIVEL_FINALIZADO;
        }

        if (!in_array($mesAtual, $nuMesAnoDisponibilidades) && $configuracaoQuestionario->tp_situacao == self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA && $mesAtual < end($nuMesAnoDisponibilidades)) {
            $novaConfiguracao['co_configuracao_questionario'] = $configuracaoQuestionario->co_configuracao_questionario;
            $novaConfiguracao['tp_situacao'] = self::TP_SITUACAO_DISPONIVEL_PROGRAMADO;
        }

        if (in_array($mesAtual, $nuMesAnoDisponibilidades) && $configuracaoQuestionario->tp_situacao == self::TP_SITUACAO_DISPONIVEL_PROGRAMADO) {
            $novaConfiguracao['co_configuracao_questionario'] = $configuracaoQuestionario->co_configuracao_questionario;
            $novaConfiguracao['tp_situacao'] = self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA;
        }

        if ($novaConfiguracao) {
            $this->getConfiguracaoQuestionarioDAO()->alterarStatus($novaConfiguracao);
        }
    }

    /**
     * Recupera o maior mes das disponibilidades no formato 'mY'.
     *
     * @param $disponibilidades
     * @return null|string
     */
    private function getPeriodoMesAnoDisponibilidadeSelecionadas($disponibilidades)
    {
        $menorMesAnoDisponibilidade = null;
        $maiorMesAnoDisponibilidade = null;
        $periodoDisponibilidadesSelecionadas = new stdClass();

        foreach ($disponibilidades as $disponibilidade) {
            $mesAnoDisponibilidade = str_pad($disponibilidade, 6, '0', STR_PAD_LEFT);
            $mesAnoDisponibilidade = substr($mesAnoDisponibilidade, 2, 4) . substr($mesAnoDisponibilidade, 0, 2);

            if ($menorMesAnoDisponibilidade == null && $maiorMesAnoDisponibilidade == null) {
                $menorMesAnoDisponibilidade = $mesAnoDisponibilidade;
                $maiorMesAnoDisponibilidade = $mesAnoDisponibilidade;
            } else {

                if ($menorMesAnoDisponibilidade > $mesAnoDisponibilidade) {
                    $menorMesAnoDisponibilidade = $mesAnoDisponibilidade;
                }

                if ($maiorMesAnoDisponibilidade < $mesAnoDisponibilidade) {
                    $maiorMesAnoDisponibilidade = $mesAnoDisponibilidade;
                }
            }
        }

        $periodoDisponibilidadesSelecionadas->menorDisponibilidade = substr($menorMesAnoDisponibilidade, 4, 2) . substr($menorMesAnoDisponibilidade, 0, 4);
        $periodoDisponibilidadesSelecionadas->maiorDisponibilidade = substr($maiorMesAnoDisponibilidade, 4, 2) . substr($maiorMesAnoDisponibilidade, 0, 4);

        return $periodoDisponibilidadesSelecionadas;
    }

    /**
     * Verifica se há vinculo de respostas ao Questionario em questão.
     *
     * @param $coConfiguracao
     * @throws Erro
     */
    private function isVinculoQuestionarioRespostas($coConfiguracao)
    {
        $pessoaRespostaQuestionario = $this->getConfiguracaoQuestionarioDAO()->getRespostasPessoaConfiguracao($coConfiguracao);
        if ($pessoaRespostaQuestionario > 0) {
            throw new Erro("Não é possível realizar a exclusão desse questionário, há respostas vinculadas a ele.");
        }
    }

    /**
     *  Recupera as redisponibilidades da configuração do questionário e retorna a situação ordenada.
     *
     * @param integer $coConfiguracao
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    private function getSituacaoRedisponibilidade($coConfiguracao, $redisponibilidades, $situacoes)
    {
        if (count($redisponibilidades) > 0) {
            $primeiraDisponibilidade = reset($redisponibilidades);
            array_push($situacoes, $primeiraDisponibilidade->tp_situacao);
        }

        if (count($redisponibilidades) > 1) {
            $segundaDisponibilidade = end($redisponibilidades);
            array_push($situacoes, $segundaDisponibilidade->tp_situacao);
        }
        asort($situacoes);

        return $situacoes;
    }

    /**
     * Valida qual status está a configuração do questionário e atribui um novo status e retorna a mensagem correta para o usuário.
     *
     * @param stdClass $configuracao
     * @param stdClass $statusTO
     * @param string $noPublicoAlvo
     * @throws Aviso
     */
    private function ativacaoInativacaoConfiguracao($configuracao, $statusTO, $noPublicoAlvo)
    {
        if ($configuracao->st_ativo) {
            $configuracao->st_ativo = 0;

            $statusTO->msg = $this->msgStatusInativoConfiguracao[$configuracao->tp_situacao];

            if ($configuracao->tp_situacao == self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA || $configuracao->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PROGRAMADO) {
                $statusTO->msg = $this->msgStatusInativoConfiguracao[$configuracao->tp_situacao] . $noPublicoAlvo;
            }
        } else {
            // Caso o questionário estiver "Disponível para resposta"
            if ($configuracao->tp_situacao === self::TP_SITUACAO_DISPONIVEL_PARA_RESPOSTA) {
                throw new Aviso('Para ativar esse questionário realize a alteração da configuração e informe o status da configuração como "Ativa".');
            }

            $configuracao->st_ativo = 1;
            $statusTO->msg = $this->msgStatusAtivoConfiguracao[$configuracao->tp_situacao];
        }

        $configuracaoQuestionario['st_ativo'] = $configuracao->st_ativo;
        $configuracaoQuestionario['co_configuracao_questionario'] = $configuracao->co_configuracao_questionario;
        $this->getConfiguracaoQuestionarioDAO()->alterarStatus($configuracaoQuestionario);
    }

    /**
     * Valida qual status está a redisponibilidade da configuração do questionário e atribui um novo status e retorna a mensagem correta para o usuário.
     *
     * @param stdClass $configuracao
     * @param stdClass $statusTO
     * @param array $redisponibilidades
     * @throws Aviso
     */
    private function ativacaoInativacaoRedisponibilidade($configuracao, $statusTO, $redisponibilidades)
    {
        array_push($redisponibilidades, $configuracao);

        foreach ($redisponibilidades as $redisponibilidade) {

            if ($redisponibilidade->st_ativo) {
                $redisponibilidade->st_ativo = 0;
                $statusTO->msg = $this->msgInativoRedisponibilidade;
            } else {
                $redisponibilidade->st_ativo = 1;
                $statusTO->msg = $this->msgAtivoRedisponibilidade;
            }

            $configuracaoQuestionario['st_ativo'] = $redisponibilidade->st_ativo;
            $configuracaoQuestionario['co_configuracao_questionario'] = $redisponibilidade->co_configuracao_questionario;
            $this->getConfiguracaoQuestionarioDAO()->alterarStatus($configuracaoQuestionario);
        }
    }

    /**
     *  Recupera abrangencias do pai e salva no filho.
     *
     * @param stdClass $redisponibilidadeTO
     * @param $redisponibilidade
     * @throws Erro
     */
    private function salvarAbrangenciaRedisponibilidade(stdClass $redisponibilidadeTO, $redisponibilidade)
    {
        // CURSO
        if ($redisponibilidade->tp_frente === self::FRENTE_QUESTIONARIO_CURSO) {
            $coCursos = $this->getConfiguracaoQuestionarioFrenteCursoBO()->getCursoPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);

            if (!empty($coCursos)) {
                $coCursos = array_column($coCursos, 'co_curso');
                $this->salvarFrenteCurso($coCursos, $redisponibilidade->co_configuracao_questionario);
            }
        }

        // TURMA
        if ($redisponibilidade->tp_frente === self::FRENTE_QUESTIONARIO_TURMA) {
            $coTurmas = $this->getConfiguracaoQuestionarioFrenteTurmaBO()->getTurmaPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);

            if (!empty($coTurmas)) {
                $coTurmas = array_column($coTurmas, 'co_turma');
                $this->salvarFrenteTurma($coTurmas, $redisponibilidade->co_tipo_iniciativa, $redisponibilidade->co_configuracao_questionario);
            }
        }

        // EIXO TECNOLOGICO
        if ($redisponibilidade->tp_frente === self::FRENTE_QUESTIONARIO_TURMA) {
            $coEixosTecnologicos = $this->getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()->getEixoTecnologicoPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);

            if (!empty($coEixosTecnologicos)) {
                $coTurmas = array_column($coEixosTecnologicos, 'co_eixo_tecnologico');
                $this->salvarFrenteEixoTecnologico($coEixosTecnologicos, $redisponibilidade->co_configuracao_questionario);
            }
        }

        // UNIDADE ENSINO / UNIDADE ENSINO REMOTA
        if ($redisponibilidade->tp_frente === self::FRENTE_QUESTIONARIO_UNIDADE_ENSINO) {
            $coUnidadesEnsinos = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()->getUnidadeEnsinoPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);
            $coUnidadesEnsinosRemotas = $this->getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()->getUnidadeEnsinoRemotaPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);

            if (!empty($coUnidadesEnsinos)) {
                $coUnidadesEnsinos = array_column($coUnidadesEnsinos, 'co_unidade_ensino');
                $this->salvarFrenteUnidadeEnsino($coUnidadesEnsinos, $redisponibilidade->co_configuracao_questionario);
            }

            if (!empty($coUnidadesEnsinosRemotas)) {
                $coUnidadesEnsinosRemotas = array_column($coUnidadesEnsinosRemotas, 'co_unidade_ensino_remota');
                $this->salvarFrenteUnidadeEnsinoRemota($coUnidadesEnsinosRemotas, $redisponibilidade->co_configuracao_questionario);
            }
        }
        // REGIÃO
        $coRegioes = $this->getConfiguracaoQuestionarioRegiaoBO()->getRegiaoPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);

        if (!empty($coRegioes)) {
            $coRegioes = array_column($coRegioes, 'co_regiao');
            $this->salvarRegioes($coRegioes, $redisponibilidade->co_configuracao_questionario);
        }

        // UF
        $coUfs = $this->getConfiguracaoQuestionarioUfBO()->getUfPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);

        if (!empty($coUfs)) {
            $coUfs = array_column($coUfs, 'sg_uf');
            $this->salvarUfs($coUfs, $redisponibilidade->co_configuracao_questionario);
        }

        // MUNICÍPIO
        $coMunicipios = $this->getConfiguracaoQuestionarioMunicipioBO()->getMunicipioPorConfiguracao($redisponibilidadeTO->coConfQuestionarioPai);

        if (!empty($coMunicipios)) {
            $coMunicipios = array_column($coMunicipios, 'co_municipio');
            $this->salvarMunicipio($coMunicipios, $redisponibilidade->co_configuracao_questionario);
        }
    }

    /**
     * Recupera dados (Esfera e sub esfera Administrativa) da configuração do questionário Pactuação e grava no filho.
     *
     * @param stdClass $redisponibilizacaoPactuacao
     * @param $redisponibilidade
     * @throws Erro
     */
    private function salvarAbrangenciaPactuacao(stdClass $redisponibilizacaoPactuacao, $redisponibilidade)
    {
        $confPactuacao = $this->getConfiguracaoQuestionarioPactuacaoBO()->getConfiguracaoPactuacaoPorConfQuestionario($redisponibilidade->co_conf_questionario_pai);
        $coEsferaAdministrativa = $this->getConfiguracaoQuestionarioEsferaAdministrativaBO()->getEsferasAdministrativaPorConfiguracao($confPactuacao->co_configuracao_questionario_pactuacao);

        if (!empty($coEsferaAdministrativa)) {
            $coEsferaAdministrativa = array_column($coEsferaAdministrativa, 'co_dependencia_admin');
            $this->salvarEsferasAdministrativas($coEsferaAdministrativa, $redisponibilizacaoPactuacao->co_configuracao_questionario_pactuacao);
        }

        $coSubEsferaAdministrativa = $this->getConfiguracaoQuestionarioSubEsferaAdministrativaBO()->getSubEsferaAdministrativaPorConfiguracao($confPactuacao->co_configuracao_questionario_pactuacao);
        if (!empty($coSubEsferaAdministrativa)) {
            $coSubEsferaAdministrativa = array_column($coSubEsferaAdministrativa, 'co_subdependencia_admin');
            $this->salvarSubEsferasAdministrativas($coSubEsferaAdministrativa, $redisponibilizacaoPactuacao->co_configuracao_questionario_pactuacao);
        }
    }

    /**
     * Retorna os emails da Mantendora e das Pessoas que possuem Perfil em suas respetivas Unidades de Ensino.
     *
     * @param $configuracao
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    private function getEmailsMantenedoraPorAbrangenciaQuestionario($configuracao)
    {
        $emailMantenedoras = $this->getConfiguracaoQuestionarioDAO()->getEmailMantenedorasPorAbrangenciaQuestionario($configuracao);
        $emailPessoasUnidadeEnsinos = $this->getConfiguracaoQuestionarioDAO()->getEmailPessoasUnidadeEnsinoMantenedoraPorAbrangenciaQuestionario($configuracao);
        array_merge($emailMantenedoras, $emailPessoasUnidadeEnsinos);

        return array_merge($emailMantenedoras, $emailPessoasUnidadeEnsinos);
    }

    /**
     * Retorna os emails da Mantendora depactuação e das Pessoas que possuem Perfil em suas respetivas Unidades de Ensino.
     *
     * @param $configuracao
     * @return array
     * @throws Zend_Db_Select_Exception
     */
    private function getEmailsMantenedoraPactuacaoPorAbrangenciaQuestionario($configuracao)
    {
        $emailMantenedoras = $this->getConfiguracaoQuestionarioDAO()->getEmailMantenedoraPactuacaoPorAbrangenciaQuestionario($configuracao);
        $emailPessoasUnidadeEnsinos = $this->getConfiguracaoQuestionarioDAO()->getEmailUnidadesEnsinosPactuacaoPorAbrangenciaQuestionario($configuracao);

        return array_merge($emailMantenedoras, $emailPessoasUnidadeEnsinos);
    }

    /**
     * Envia e-mails de informativos de acordo a configurações e pessoas.
     *
     * @param $configuracao
     * @param $pessoas
     */
    private function enviarEmailsQuestionario($configuracao, $pessoas)
    {
        $emailsTeste = $this->getParametrosConfiguracaoBO()->getEmailsDestinariosTeste();

        foreach ($pessoas as $pessoa) {
            $email = Email::newInstance();

            if (empty($emailsTeste)) {
                $email->setDestinatario($pessoa->email);
            } else {
                $email->setDestinatarios($emailsTeste);
            }

            $email->setAssunto(static::ASSUNTO_EMAIL_INFORMATIVO);
            $corpoEmail = $this->getCorpoEmail($pessoa->nome, $configuracao->ds_info);
            $email->setCorpoEmail($corpoEmail);
            $email->enviar();
        }
    }

    /**
     * Retorna uma instância de ParametrosConfiguracaoBO.
     *
     * @return ParametrosConfiguracaoBO
     */
    private function getParametrosConfiguracaoBO()
    {
        return new ParametrosConfiguracaoBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioDAO.
     *
     * @return ConfiguracaoQuestionarioDAO
     */
    private function getConfiguracaoQuestionarioDAO()
    {
        return new ConfiguracaoQuestionarioDAO();
    }

    /**
     * Retorna uma instância de AdesaoSisuTecnicoEditalBO.
     *
     * @return AdesaoSisuTecnicoEditalBO
     */
    private function getAdesaoSisuTecnicoEditalBO()
    {
        return new AdesaoSisuTecnicoEditalBO();
    }

    /**
     * Retorna uma instância de AdesaoSisuTecnicoTurmaBO.
     *
     * @return AdesaoSisuTecnicoTurmaBO
     */
    private function getAdesaoSisuTecnicoTurmaBO()
    {
        return new AdesaoSisuTecnicoTurmaBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioHistoricoBO.
     *
     * @return ConfiguracaoQuestionarioHistoricoBO
     */
    private function getConfiguracaoQuestionarioHistoricoBO()
    {
        return new ConfiguracaoQuestionarioHistoricoBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioDisponibilidadeBO.
     *
     * @return ConfiguracaoQuestionarioDisponibilidadeBO
     */
    private function getConfiguracaoQuestionarioDisponibilidadeBO()
    {
        return new ConfiguracaoQuestionarioDisponibilidadeBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioRegiaoBO.
     *
     * @return ConfiguracaoQuestionarioRegiaoBO
     */
    private function getConfiguracaoQuestionarioRegiaoBO()
    {
        return new ConfiguracaoQuestionarioRegiaoBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioUfBO.
     *
     * @return ConfiguracaoQuestionarioUfBO
     */
    private function getConfiguracaoQuestionarioUfBO()
    {
        return new ConfiguracaoQuestionarioUfBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioMunicipioBO.
     *
     * @return ConfiguracaoQuestionarioMunicipioBO
     */
    private function getConfiguracaoQuestionarioMunicipioBO()
    {
        return new ConfiguracaoQuestionarioMunicipioBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioFrenteCursoBO.
     *
     * @return ConfiguracaoQuestionarioFrenteCursoBO
     */
    private function getConfiguracaoQuestionarioFrenteCursoBO()
    {
        return new ConfiguracaoQuestionarioFrenteCursoBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioFrenteTurmaBO.
     *
     * @return ConfiguracaoQuestionarioFrenteTurmaBO
     */
    private function getConfiguracaoQuestionarioFrenteTurmaBO()
    {
        return new ConfiguracaoQuestionarioFrenteTurmaBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioFrenteUnidadeEnsinoBO.
     *
     * @return ConfiguracaoQuestionarioFrenteUnidadeEnsinoBO
     */
    private function getConfiguracaoQuestionarioFrenteUnidadeEnsinoBO()
    {
        return new ConfiguracaoQuestionarioFrenteUnidadeEnsinoBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO.
     *
     * @return ConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO
     */
    private function getConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO()
    {
        return new ConfiguracaoQuestionarioFrenteUnidadeEnsinoRemotaBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioFrenteEixoTecnologicoBO.
     *
     * @return ConfiguracaoQuestionarioFrenteEixoTecnologicoBO
     */
    private function getConfiguracaoQuestionarioFrenteEixoTecnologicoBO()
    {
        return new ConfiguracaoQuestionarioFrenteEixoTecnologicoBO();
    }

    /**
     * Retorna uma instância de getEstadosBO.
     *
     * @return getEstadosBO
     */
    private function getEstadosBO()
    {
        return new EstadosBO();
    }

    /**
     * Retorna uma instância de MunicipiosBO.
     *
     * @return MunicipiosBO
     */
    private function getMuncipiosBO()
    {
        return new MunicipiosBO();
    }

    /**
     * Retorna uma instância de TipoCursoBO.
     *
     * @return TipoCursoBO
     */
    private function getTipoCursoBO()
    {
        return new TipoCursoBO();
    }

    /**
     * Retorna uma instância de InformativoBO.
     *
     * @return InformativoBO
     */
    private function getInformativoBO()
    {
        return new InformativoBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioPactuacaoBO.
     *
     * @return ConfiguracaoQuestionarioPactuacaoBO
     */
    private function getConfiguracaoQuestionarioPactuacaoBO()
    {
        return new ConfiguracaoQuestionarioPactuacaoBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioEsferaAdministrativaBO.
     *
     * @return ConfiguracaoQuestionarioEsferaAdministrativaBO
     */
    private function getConfiguracaoQuestionarioEsferaAdministrativaBO()
    {
        return new ConfiguracaoQuestionarioEsferaAdministrativaBO();
    }

    /**
     * Retorna uma instância de ConfiguracaoQuestionarioSubEsferaAdministrativaBO.
     *
     * @return ConfiguracaoQuestionarioSubEsferaAdministrativaBO
     */
    private function getConfiguracaoQuestionarioSubEsferaAdministrativaBO()
    {
        return new ConfiguracaoQuestionarioSubEsferaAdministrativaBO();
    }

}
