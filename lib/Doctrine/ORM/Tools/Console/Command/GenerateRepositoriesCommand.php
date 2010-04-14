<?php
/*
 *  $Id$
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM\Tools\Console\Command;

use Symfony\Components\Console\Input\InputArgument,
    Symfony\Components\Console\Input\InputOption,
    Symfony\Components\Console,
    Doctrine\ORM\Tools\Console\MetadataFilter;

/**
 * Command to generate repository classes for mapping information.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class GenerateRepositoriesCommand extends Console\Command\Command
{
    private static $_template =
'<?php

namespace <namespace>;

use \Doctrine\ORM\EntityRepository;

/**
 * <className>
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class <className> extends EntityRepository
{
}';

    /**
     * @see Console\Command\Command
     */
    protected function configure()
    {
        $this
        ->setName('orm:generate-repositories')
        ->setDescription('Generate repository classes from your mapping information.')
        ->setDefinition(array(
            new InputOption(
                'filter', null, InputOption::PARAMETER_REQUIRED | InputOption::PARAMETER_IS_ARRAY,
                'A string pattern used to match entities that should be processed.'
            ),
            new InputArgument(
                'dest-path', InputArgument::REQUIRED, 'The path to generate your repository classes.'
            )
        ))
        ->setHelp(<<<EOT
Generate repository classes from your mapping information.
EOT
        );
    }

    /**
     * @see Console\Command\Command
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $em = $this->getHelper('em')->getEntityManager();
        
        $metadatas = $em->getMetadataFactory()->getAllMetadata();
        $metadatas = MetadataFilter::filter($metadatas, $input->getOption('filter'));

        // Process destination directory
        $destPath = realpath($input->getArgument('dest-path'));

        if ( ! file_exists($destPath)) {
            throw new \InvalidArgumentException(
                sprintf("Entities destination directory '<info>%s</info>' does not exist.", $destPath)
            );
        } else if ( ! is_writable($destPath)) {
            throw new \InvalidArgumentException(
                sprintf("Entities destination directory '<info>%s</info>' does not have write permissions.", $destPath)
            );
        }

        if ( count($metadatas)) {
            $numRepositories = 0;

            foreach ($metadatas as $metadata) {
                if ($metadata->customRepositoryClassName) {
                    $output->write(
                        sprintf('Processing repository "<info>%s</info>"', $metadata->customRepositoryClassName) . PHP_EOL
                    );

                    $this->_generateRepositoryClass($metadata, $destPath);

                    $numRepositories++;
                }
            }

            if ($numRepositories) {
                // Outputting information message
                $output->write(PHP_EOL . sprintf('Repository classes generated to "<info>%s</INFO>"', $destPath) . PHP_EOL);
            } else {
                $output->write('No Repository classes were found to be processed.' . PHP_EOL);
            }
        } else {
            $output->write('No Metadata Classes to process.' . PHP_EOL);
        }
    }

    private function _generateRepositoryClass($metadata, $destPath)
    {
        $code = $this->_generateRepositoryClass($metadata->customRepositoryClassName);

        $path = $destPath . DIRECTORY_SEPARATOR
              . str_replace('\\', \DIRECTORY_SEPARATOR, $metadata->customRepositoryClassName) . '.php';
        $dir = dirname($path);

        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $code);
    }
}