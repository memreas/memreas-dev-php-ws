<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE. This software consists of voluntary contributions made by many individuals and is licensed under the MIT license. For more information, see <http://www.doctrine-project.org>.
 */
namespace Doctrine\Common\Persistence;

/**
 * Base class to simplify ObjectManager decorators
 *
 * @license http://opensource.org/licenses/MIT MIT
 * @link www.doctrine-project.org
 * @since 2.4
 * @author Lars Strojny <lars@strojny.net>
 */
abstract class ObjectManagerDecorator implements ObjectManager {
	/**
	 *
	 * @var ObjectManager
	 */
	protected $wrapped;
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function find($className, $id) {
		return $this->wrapped->find ( $className, $id );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function persist($object) {
		return $this->wrapped->persist ( $object );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function remove($object) {
		return $this->wrapped->remove ( $object );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function merge($object) {
		return $this->wrapped->merge ( $object );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function clear($objectName = null) {
		return $this->wrapped->clear ( $objectName );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function detach($object) {
		return $this->wrapped->detach ( $object );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function refresh($object) {
		return $this->wrapped->refresh ( $object );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function flush() {
		return $this->wrapped->flush ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function getRepository($className) {
		return $this->wrapped->getRepository ( $className );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function getClassMetadata($className) {
		return $this->wrapped->getClassMetadata ( $className );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function getMetadataFactory() {
		return $this->wrapped->getMetadataFactory ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function initializeObject($obj) {
		return $this->wrapped->initializeObject ( $obj );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function contains($object) {
		return $this->wrapped->contains ( $object );
	}
}
