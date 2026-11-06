<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/** Supplies scripted answers without terminal-specific hidden-input executables. */
final class ScriptedQuestionHelper extends QuestionHelper
{
    public function __construct(private array $answers)
    {
    }

    public function ask(InputInterface $input, OutputInterface $output, Question $question): mixed
    {
        $output->writeln($question->getQuestion());
        $attempts = 0;
        do {
            if ($this->answers === []) {
                throw new \RuntimeException('No scripted answer remains.');
            }
            $answer = array_shift($this->answers);
            if ($question->getNormalizer() !== null) {
                $answer = ($question->getNormalizer())($answer);
            }
            try {
                return $question->getValidator() !== null ? ($question->getValidator())($answer) : $answer;
            } catch (\Throwable $exception) {
                ++$attempts;
                $output->writeln('<error>'.$exception->getMessage().'</error>');
                if ($question->getMaxAttempts() !== null && $attempts >= $question->getMaxAttempts()) {
                    throw $exception;
                }
            }
        } while (true);
    }
}

