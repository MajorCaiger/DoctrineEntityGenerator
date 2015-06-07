<?php

namespace MajorCaiger\DoctrineEntityGenerator\Controller;

use Zend\Mvc\Controller\AbstractActionController;

/**
 * Generate Controller
 *
 * @author Rob Caiger <rob@clocal.co.uk>
 */
class GenerateController extends AbstractActionController
{
    public function generateAction()
    {
        $generator = $this->getServiceLocator()->get('DoctrineEntityGenerator');

        return $generator->generate();
    }
}
