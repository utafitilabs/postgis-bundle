<?php

declare(strict_types=1);

/*
 * This file is part of the UtafitiLabs PostGIS Bundle.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace UtafitiLabs\PostGISBundle\ORM\Functions;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * Generic base for PostGIS ST_* DQL functions.
 *
 * Parses `NAME(arg[, arg]...)` once, so every concrete function is a tiny declaration
 * of its SQL name and argument count — mirroring the breadth of PostGIS / the R `sf`
 * package without a bespoke parser per function:
 *
 *   final class StArea extends AbstractSpatialFunction {
 *       protected function functionName(): string { return 'ST_Area'; }
 *   }
 *
 * Arguments accept any DQL primary (path expressions like `e.geom`, input parameters,
 * literals, and nested functions), so calls compose freely.
 */
abstract class AbstractSpatialFunction extends FunctionNode
{
    /** @var list<Node|string> */
    protected array $arguments = [];

    /** The SQL function name to emit, e.g. 'ST_Area'. */
    abstract protected function functionName(): string;

    /** Minimum (and, by default, exact) number of arguments. */
    protected function minArgs(): int
    {
        return 1;
    }

    /** Maximum number of arguments; -1 means unbounded. Defaults to minArgs(). */
    protected function maxArgs(): int
    {
        return $this->minArgs();
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->arguments[] = $parser->ArithmeticPrimary();

        // Remaining required arguments.
        for ($i = 1, $min = $this->minArgs(); $i < $min; ++$i) {
            $parser->match(TokenType::T_COMMA);
            $this->arguments[] = $parser->ArithmeticPrimary();
        }

        // Optional arguments up to the maximum.
        $max = $this->maxArgs();
        while (
            (-1 === $max || \count($this->arguments) < $max)
            && $parser->getLexer()->isNextToken(TokenType::T_COMMA)
        ) {
            $parser->match(TokenType::T_COMMA);
            $this->arguments[] = $parser->ArithmeticPrimary();
        }

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        $args = array_map(
            static fn (Node|string $node): string => $node instanceof Node ? $node->dispatch($sqlWalker) : $node,
            $this->arguments,
        );

        return \sprintf('%s(%s)', $this->functionName(), implode(', ', $args));
    }
}
