<?php
namespace Guywithnose\ReleaseNotes\Prompt;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PromptFactory
{
    /** @type \Symfony\Component\Console\Input\InputInterface The command output. */
    protected $_input;

    /** @type \Symfony\Component\Console\Output\OutputInterface The command output. */
    protected $_output;

    /** @type \Symfony\Component\Console\Helper\QuestionHelper The question helper. */
    protected $_questionHelper;

    /** @type \Symfony\Component\Console\Helper\FormatterHelper The formatter helper. */
    protected $_formatter;

    /**
     * Initialize the prompt factory.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param \Symfony\Component\Console\Helper\QuestionHelper $questionHelper The question helper.
     * @param \Symfony\Component\Console\Helper\FormatterHelper $formatter The formatter helper.
     */
    public function __construct(InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper, FormatterHelper $formatter)
    {
        $this->_input = $input;
        $this->_output = $output;
        $this->_questionHelper = $questionHelper;
        $this->_formatter = $formatter;
    }

    /**
     * Create the prompt.
     *
     * @param string $question The question to ask.
     * @param mixed $default The default answer to the prompt.
     * @param array $choices The chocies/suggestions for the prompt.
     * @param string $preamble A preamble to display before asking the question.
     * @param boolean $choicesOnly True if the choices are the only available answers, false otherwise.
     * @return \Guywithnose\ReleaseNotes\Prompt The prompt object.
     */
    public function create($question, $default = null, array $choices = [], $preamble = '', $choicesOnly = true)
    {
        return new Prompt(
            $this->_input,
            $this->_output,
            $this->_questionHelper,
            $this->_formatter,
            $question,
            $default,
            $choices,
            $preamble,
            $choicesOnly
        );
    }

    /**
     * Create and immediately invoke the prompt returning the result.
     *
     * @param string $question The question to ask.
     * @param mixed $default The default answer to the prompt.
     * @param array $choices The chocies/suggestions for the prompt.
     * @param string $preamble A preamble to display before asking the question.
     * @param boolean $choicesOnly True if the choices are the only available answers, false otherwise.
     * @return mixed The response from the prompt.
     */
    public function invoke($question, $default = null, array $choices = [], $preamble = '', $choicesOnly = true)
    {
        $prompt = $this->create($question, $default, $choices, $preamble, $choicesOnly);

        return $prompt();
    }
}
