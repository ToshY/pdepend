<?php
/**
 * This file is part of PHP_Depend.
 * 
 * PHP Version 5
 *
 * Copyright (c) 2008, Manuel Pichler <mapi@pmanuel-pichler.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Log
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.manuel-pichler.de/
 */

require_once 'PHP/Depend/Code/NodeVisitorI.php';
require_once 'PHP/Depend/Code/NodeVisitor/AbstractDefaultVisitor.php';
require_once 'PHP/Depend/Log/LoggerI.php';

/**
 * Generates an xml document with the aggregated metrics. The format is borrowed
 * from <a href="http://clarkware.com/software/JDepend.html">JDepend</a>. 
 *
 * @category   QualityAssurance
 * @package    PHP_Depend
 * @subpackage Log
 * @author     Manuel Pichler <mapi@manuel-pichler.de>
 * @copyright  2008 Manuel Pichler. All rights reserved.
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.manuel-pichler.de/
 */
class PHP_Depend_Log_Jdepend_Xml 
       extends PHP_Depend_Code_NodeVisitor_AbstractDefaultVisitor
    implements PHP_Depend_Log_LoggerI
{
    protected $fileName = '';
    
    /**
     * The raw {@link PHP_Depend_Code_Package} instances.
     *
     * @type PHP_Depend_Code_NodeIterator
     * @var PHP_Depend_Code_NodeIterator $code
     */
    protected $code = null;
    
    /**
     * Set of all analyzed files.
     *
     * @type array<PHP_Depend_Code_File>
     * @var array(string=>PHP_Depend_Code_File) $fileSet
     */
    protected $fileSet = array();
    
    /**
     * List of all generated project metrics.
     *
     * @type array<mixed>
     * @var array(string=>mixed) $projectMetrics
     */
    protected $projectMetrics = array();
    
    /**
     * List of all collected node metrics.
     *
     * @type array<array>
     * @var array(string=>array) $nodeMetrics
     */
    protected $nodeMetrics = array();
    
    /**
     * The depedency result set.
     *
     * @type PHP_Depend_Metrics_Dependency_Analyzer
     * @var PHP_Depend_Metrics_Dependency_Analyzer $analyzer
     */
    protected $analyzer = null;
    
    /**
     * The Packages dom element.
     *
     * @type DOMElement
     * @var DOMElement $packages
     */
    protected $packages = null;
    
    /**
     * The Cycles dom element.
     *
     * @type DOMElement
     * @var DOMElement $cycles
     */
    protected $cycles = null;
    
    /**
     * The concrete classes element for the current package.
     *
     * @type DOMElement
     * @var DOMElement $concreteClasses
     */
    protected $concreteClasses = null;
    
    /**
     * The abstract classes element for the current package.
     *
     * @type DOMElement
     * @var DOMElement $abstractClasses
     */
    protected $abstractClasses = null;
    
    /**
     * Constructs a new logger for the given output file.
     *
     * @param string $fileName The log output file
     */
    public function __construct($fileName)
    {
        $this->fileName = $fileName;
    }
    
    /**
     * Sets the context code nodes.
     *
     * @param PHP_Depend_Code_NodeIterator $code The code nodes.
     * 
     * @return void
     */
    public function setCode(PHP_Depend_Code_NodeIterator $code)
    {
        $this->code = $code;
    }
    
    /**
     * Adds an analyzer to log. If this logger accepts the given analyzer it
     * with return <b>true</b>, otherwise the return value is <b>false</b>.
     *
     * @param PHP_Depend_Metrics_AnalyzerI $analyzer The analyzer to log.
     * 
     * @return boolean
     */
    public function log(PHP_Depend_Metrics_AnalyzerI $analyzer)
    {
        if ($analyzer instanceof PHP_Depend_Metrics_Dependency_Analyzer) {
            $this->analyzer = $analyzer;

            return true;
        }
        return false;
    }
    
    /**
     * Closes the logger process and writes the output file.
     *
     * @return void
     */
    public function close()
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        
        $dom->formatOutput = true;

        $jdepend = $dom->createElement('PDepend');
        
        $this->packages = $jdepend->appendChild($dom->createElement('Packages'));
        $this->cycles   = $jdepend->appendChild($dom->createElement('Cycles'));
        
        foreach ($this->code as $node) {
            $node->accept($this);
        }
        
        $dom->appendChild($jdepend);
        $dom->save($this->fileName);
    }
    
    /**
     * Visits a class node. 
     *
     * @param PHP_Depend_Code_Class $class The current class node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitorI::visitClass()
     */
    public function visitClass(PHP_Depend_Code_Class $class)
    {
        $doc = $this->packages->ownerDocument;
        
        $classXml = $doc->createElement('Class');
        $classXml->setAttribute('sourceFile', (string) $class->getSourceFile());
        $classXml->appendChild($doc->createTextNode($class->getName()));
        
        if ($class->isAbstract()) {
            $this->abstractClasses->appendChild($classXml);
        } else {
            $this->concreteClasses->appendChild($classXml);
        }
    }
    
    /**
     * Visits a code interface object.
     *
     * @param PHP_Depend_Code_Interface $interface The context code interface.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitorI::visitInterface()
     */
    public function visitInterface(PHP_Depend_Code_Interface $interface)
    {
        $doc = $this->abstractClasses->ownerDocument;
        
        $classXml = $doc->createElement('Class');
        $classXml->setAttribute('sourceFile', (string) $interface->getSourceFile());
        $classXml->appendChild($doc->createTextNode($interface->getName()));
        
        $this->abstractClasses->appendChild($classXml);
    }
    
    /**
     * Visits a package node. 
     *
     * @param PHP_Depend_Code_Class $package The package class node.
     * 
     * @return void
     * @see PHP_Depend_Code_NodeVisitorI::visitPackage()
     */
    public function visitPackage(PHP_Depend_Code_Package $package)
    {
        $doc = $this->packages->ownerDocument;
        
        $this->concreteClasses = $doc->createElement('ConcreteClasses');
        $this->abstractClasses = $doc->createElement('AbstractClasses');

        $packageXml = $doc->createElement('Package');
        $packageXml->setAttribute('name', $package->getName());
        
        $stats = $this->analyzer->getStats($package);
        
        $statsXml = $doc->createElement('Stats');
        $statsXml->appendChild($doc->createElement('TotalClasses'))
                 ->appendChild($doc->createTextNode($stats['tc']));
        $statsXml->appendChild($doc->createElement('ConcreteClasses'))
                 ->appendChild($doc->createTextNode($stats['cc']));
        $statsXml->appendChild($doc->createElement('AbstractClasses'))
                 ->appendChild($doc->createTextNode($stats['ac']));
        $statsXml->appendChild($doc->createElement('Ca'))
                 ->appendChild($doc->createTextNode($stats['ca']));
        $statsXml->appendChild($doc->createElement('Ce'))
                 ->appendChild($doc->createTextNode($stats['ce']));
        $statsXml->appendChild($doc->createElement('A'))
                 ->appendChild($doc->createTextNode($stats['a'])); 
        $statsXml->appendChild($doc->createElement('I'))
                 ->appendChild($doc->createTextNode($stats['i']));
        $statsXml->appendChild($doc->createElement('D'))
                 ->appendChild($doc->createTextNode($stats['d']));
                 
        $dependsUpon = $doc->createElement('DependsUpon');
        foreach ($this->analyzer->getEfferents($package) as $efferent) {
            $efferentXml = $doc->createElement('Package');
            $efferentXml->appendChild($doc->createTextNode($efferent->getName()));
            
            $dependsUpon->appendChild($efferentXml);
        }
        
        $usedBy = $doc->createElement('UsedBy');
        foreach ($this->analyzer->getAfferents($package) as $afferent) {
            $afferentXml = $doc->createElement('Package');
            $afferentXml->appendChild($doc->createTextNode($afferent->getName()));
            
            $usedBy->appendChild($afferentXml);
        }
        
        $packageXml->appendChild($statsXml);
        $packageXml->appendChild($this->concreteClasses);
        $packageXml->appendChild($this->abstractClasses);
        $packageXml->appendChild($dependsUpon);
        $packageXml->appendChild($usedBy);
        
        if (($cycles = $this->analyzer->getCycle($package)) !== null) {
            $cycleXml = $doc->createElement('Package');
            $cycleXml->setAttribute('Name', $package->getName());
            
            foreach ($cycles as $cycle) {
                $cycleXml->appendChild($doc->createElement('Package'))
                         ->appendChild($doc->createTextNode($cycle->getName()));
            }
            
            $this->cycles->appendChild($cycleXml);
        }
        
        foreach ($package->getTypes() as $type) {
            $type->accept($this);
        }
            
        $this->packages->appendChild($packageXml);
    }
}