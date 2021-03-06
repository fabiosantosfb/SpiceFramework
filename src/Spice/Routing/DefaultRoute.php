<?php
/**
 * Define a classe Spice\Routing\DefaultRoute.
 *
 * @license http://opensource.org/licenses/GPL-3.0 GNU Public License
 * @author Henrique Barcelos <rick.hjpbarcelos@gmail.com>
 */
namespace Spice\Routing;

use Spice\Http\RequestInterface;

/**
 * Representa uma rota parametrizada.
 * Parâmetros são denotados na forma `{parametro}`.
 *
 * Exemplo:
 * <code>
 *  $routing = new \Spice\Routing\DefaultRouting(
 *      'my_route', 
 *      '/foo/bar/{action}'
 *  );
 * </code>
 *
 * A rota do exemplo acima tem um parâmetro, chamado `action`.
 *
 * @package Routing
 */
class DefaultRoute extends AbstractRoute {
    private $required;
    private $paramList;
    private $matchRegex;
    
    const PARAM_PATTERN = '/\{([\w-_]+)\}/';

    /**
     * Inicializa uma rota parametrizada.
     *
     * Os parâmetros são declarados em `$mathPattern` e são denotados 
     * na forma `{parametro}`.
     *
     * Exemplo:
     * <code>
     *  $routing = new \Spice\Routing\DefaultRouting(
     *      'my_route', 
     *      '/foo/bar/{action}'
     *  );
     * </code>
     *
     * A rota do exemplo acima tem um parâmetro, chamado `action`.
     *
     * Para mais detalhes sobre os parâmetros:
     * @see self::setDefaults()
     * @see self::setParamsRequiredMap()
     *
     * @inheritdoc
     * 
     * @param array $requiredMap [OPCIONAL] Um array associativo indicando
     *      se os parâmetros são obrigatórios ou não.
     */
    public function __construct(
        $name, 
        $matchPattern, 
        array $defaultsMap = array(), 
        array $requiredMap = array()
    ) {
        parent::__construct($name, $matchPattern, $defaultsMap);
        $this->setParamsRequiredMap($requiredMap);
    }

    /**
     * Estabelece o padrão de "casamento" da rota com uma requisição.
     *
     * Parâmetros são denotados na forma `{parametro}`.
     *
     * Exemplo:
     * <code>
     *  $routing = new \Spice\Routing\DefaultRouting(
     *      'my_route', 
     *      '/foo/bar/{action}'
     *  );
     * </code>
     *
     * A rota do exemplo acima tem um parâmetro, chamado `action`.
     *
     * **IMPORTANTE:** Por razões de performance, não há nenhuma verificação
     * sintática para o padrão de "casamento". Caso não esteja na forma
     * indicada, será tratado como literal.
     *
     * Por padrão, todos os parâmetros encontrados serão obrigatórios.
     * Caso esse comportamento não seja desejado, é necessário alterar
     * a obrigatoriedade do parâmetro através do método `setParamRequired`.
     * 
     * Os nomes de parâmetros devem conter apenas caracteres alfanuméricos,
     * sublinhados (`_`) e traços (`-`).
     *
     * Se nenhum parâmetro é necessário para a rota, considere utilizar
     * `\Spice\Routing\StaticRoute` para uma melhor performance.
     * 
     * @param string $matchPattern Um padrão de combinação.
     *
     * @return void
     */
    public function setMatchPattern($matchPattern) {
        parent::setMatchPattern($matchPattern);
        $this->parseMatchPattern();
        $this->resetParamsRequiredMap();
    }

    /**
     * Estabelece quais parâmetros de rota são requeridos.
     *
     * O array `$requiredMap` é associativo da forma:
     * <code>
     *  array (
     *      param1 => true|false,
     *      param2 => true|false,
     *      ...
     *  )
     * </code>
     * onde cada parâmetro é configurado como requerido (`true`) ou não 
     * (`false`).
     *
     * @param array $requiredMap A especificação da obrigatoriedade dos 
     *      parâmetros da rota.
     *
     * @return void
     */
    public function setParamsRequiredMap(array $requiredMap) {
        /* $this->resetParamsRequiredMap(); */
        foreach ($requiredMap as $name => $value) {
            if(isset($this->required[$name])) {
                $this->required[$name] = (bool) $value;
            }
        }
        $this->invalidateMatchRegex();
    }

    /**
     * Reseta os parâmetros de rota são requeridos, tornando todos obrigatórios.
     *
     * @param array $requiredMap [OPCIONAL] A especificação da obrigatoriedade
     *      dos parâmetros da rota.
     */
    private function resetParamsRequiredMap() {
        $this->required = array_combine(
            $this->paramList, 
            array_fill(0, count($this->paramList), true)
        );
        $this->invalidateMatchRegex();
    }

    /**
     * Retorna um array associativo contendo a indicação do parâmetro
     * ser obrigatório ou não.
     *
     * O retorno será da forma:
     * <code>
     *  array (
     *      param1 => true|false,
     *      param2 => true|false,
     *      ...
     *  )
     * </code>
     * onde cada parâmetro é requerido (`true`) ou não (`false`).
     *
     * @return array Um array contendo os parâmetros.
     */
    public function getParamsRequiredMap() {
        return $this->required;
    }

    /**
     * Altera a obrigatoriedade de um parâmetro da rota.
     * Caso o parâmetro `$name` não exista na rota, nenhuma
     * ação será realizada.
     *
     * @param string $name O nome do parâmetro a ser alterado.
     * @param boolean $opt Se o parâmetro é ou não obrigatório.
     *
     * @return void
     */
    public function setParamRequired($name, $opt) {
        if(isset($this->required[$name])) {
            $this->required[$name] = (bool) $opt;
        }
        $this->invalidateMatchRegex();
    }

    /**
     * Verifica a obrigatoriedade de um parâmetro da rota.
     * Caso o parâmetro `$name` não exista na rota, retornará `false`.
     *
     * @param string $name O nome do parâmetro a ser alterado.
     *
     * @return void
     */
    public function isParamRequired($name) {
        if(isset($this->required[$name])) {
            return $this->required[$name];
        }
        return false;
    }

    /**
     * Retorna uma lista com o nome dos parâmetros de rota encontrados
     * no padrão de "casamento" da rota.
     *
     * @return array<string> Uma lista com os nomes dos parâmetros.
     */
    public function getParamList() {
        return $this->paramList;
    }

    /**
     * @inheritdoc
     *
     * @see \Spice\Routing\RouteInterface::reverse()
     */
    public function reverse(array $params = array()) {
        $params = $this->getDefaults() + $params;

        $diff = array_diff_key(
            array_filter($this->required),
            $params
        );

        if (!empty($diff)) {
            throw new \Spice\Routing\MissingRequiredParamException(
                sprintf(
                    "Parâmetros obrigatórios de roteamento faltando: %s.",
                    join(', ', array_keys($diff))
                )
            );
        }

        $uri = $this->getMatchPattern();
        foreach ($params as $name => $value) {
            $uri = str_ireplace("{{$name}}", $value, $uri);
        }

        // Remove parâmetros opcionais sem valor corerspondente.
        foreach ($this->required as $name => $isRequired) {
            if ($isRequired === false) {
                $uri = str_ireplace("/{{$name}}", '', $uri);
            }
        }
        return $uri;
    }

    /**
     * @inheritdoc
     *
     * **Importante:** Em caso de sucesso na combinação com a requisição,
     * parâmetros padrão da requisição serão retornados 
     *
     * @see \Spice\Routing\RouteInterface::match()
     */
    public function match(RequestInterface $request) {
        $uri = $request->getUri();
        if (preg_match($this->getMatchRegex(), $uri, $matches)) {
            $paramMatches = $this->getDefaults() + array_filter($matches);
            foreach ($paramMatches as $key => $value) {
                if (is_numeric($key)) {
                    unset($paramMatches[$key]);
                }
            }

            return new RouteMatch($this->getName(), $paramMatches);
        }
        throw new RouteMismatchException(
            sprintf(
                "A URI %s não casa com o padrão %s.",
                $uri,
                $this->getMatchPattern()
            )
        );
    }

    /**
     * Analisa uma o padrão de "casamento da rota", extraindo parâmetros.
     *
     * @return void
     */
    private function parseMatchPattern() {
        if (preg_match_all(self::PARAM_PATTERN, $this->getMatchPattern(), $matches)) {
            $this->paramList = $matches[1];
        }
    }

    /**
     * Cria uma expressão regular a partir do padrão de combinação da rota.
     *
     * @return void
     */
    private function getMatchRegex() {
        if ($this->matchRegex === null) {
            $this->matchRegex = '#^' . preg_replace(
                self::PARAM_PATTERN, '(?<$1>[^/]+)',
                $this->getMatchPattern()
            ) . '$#';
        }

        foreach ($this->required as $param => $isRequired) {
            if ($isRequired === false) {
                $this->matchRegex = preg_replace(
                    '#(' . preg_quote("/(?<{$param}>[^/]+)", "#") . ')#', 
                    "(?:$1)?",
                    $this->matchRegex
                );
            }
        }

        return $this->matchRegex;
    }

    /**
     * Invalida a expressão regular criada.
     *
     * @return void
     */
    private function invalidateMatchRegex() {
        unset($this->matchRegex);
        $this->matchRegex = null;
    }
}
