<?php
/**
 * Wrapper class for handling logged-in user in session.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace CPK\Auth;

use VuFind\Auth\Manager as BaseManager, VuFind\Db\Table\User as UserTable, VuFind\Auth\PluginManager as PluginManager, Zend\Config\Config as Config, Zend\Session\SessionManager as SessionManager, VuFind\Cookie\CookieManager, Zend\ServiceManager\ServiceLocatorAwareInterface, Zend\ServiceManager\ServiceLocatorInterface, VuFind\Exception\Auth as AuthException;

/**
 * Wrapper class for handling logged-in user in session.
 *
 * @category VuFind2
 * @package Authentication
 * @author Demian Katz <demian.katz@villanova.edu>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://www.vufind.org Main Page
 */
class Manager extends BaseManager
{

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config
     *            VuFind configuration
     */
    public function __construct(Config $config, UserTable $userTable,
        SessionManager $sessionManager, PluginManager $pm,
        CookieManager $cookieManager)
    {
        parent::__construct($config, $userTable, $sessionManager, $pm,
            $cookieManager);
    }

    /**
     * Try to log in the user using current query parameters; return User object
     * on success, throws exception on failure.
     *
     * @param \Zend\Http\PhpEnvironment\Request $request
     *            Request object containing
     *            account credentials.
     *
     * @throws AuthException
     * @return UserRow Object representing logged-in user.
     */
    public function login($request)
    {
        return parent::login($request);
    }

    /**
     * Log out the current user.
     *
     * @param string $url
     *            URL to redirect user to after logging out.
     * @param bool $destroy
     *            Should we destroy the session (true) or just reset it
     *            (false); destroy is for log out, reset is for expiration.
     * @param bool $isGlobalLogout
     *            Is global logout? Or do we want only local logout, so that
     *            we the remove current session & prompt for proper redirection?
     *
     * @return string Redirect URL (usually same as $url, but modified in
     *         some authentication modules).
     */
    public function logout($url, $destroy = true)
    {
        return parent::logout($url, $destroy);
    }

    /**
     * Tries to connect current logged in user with identity specified by token
     * user holds in cookie & we in session table.
     * Once the cookie is accessed
     * is destroyed.
     *
     * @throws AuthException
     * @throws \VuFind\Exception\PasswordSecurity
     * @return \CPK\Db\Row\UserRow $user
     */
    public function connectIdentity()
    {
        $this->checkActiveAuthIsSIM();

        try {
            $user = $this->getAuth()->connectIdentity();
        } catch (AuthException $e) {
            // Pass authentication exceptions through unmodified
            throw $e;
        } catch (\VuFind\Exception\PasswordSecurity $e) {
            // Pass password security exceptions through unmodified
            throw $e;
        } catch (\Exception $e) {
            // Catch other exceptions, log verbosely, and treat them as technical
            // difficulties
            error_log(
                "Exception in " . get_class($this) . "::login: " . $e->getMessage());
            error_log($e);
            throw new AuthException('authentication_error_technical');
        }

        // Being here means the consolidation was a success
        $user->consolidationSucceeded = true;

        // Store the user in the session and send it back to the caller:
        $this->updateSession($user);
        return $user;
    }

    /**
     * Returns account consolidation redirect & handles setting token cookie &
     * writing token to session table in DB.
     *
     * @return string $accountConsolidationRedirectUrl
     * @throws AuthException
     */
    public function getAccountConsolidationRedirectUrl()
    {
        $this->checkActiveAuthIsSIM();

        $userRow = $this->isLoggedIn();

        if (! $userRow || empty($userRow->id)) {
            throw new AuthException("Cannot consolidate empty UserRow");
        }

        return $this->getAuth()->getAccountConsolidationRedirectUrl($userRow->id);
    }

    /**
     * Returns Auth instance based on name.
     *
     * @param string $authName
     * @return \VuFind\Auth\AbstractBase
     */
    public function getAuthInstance($authName = null)
    {
        if (empty($authName)) {
            return $this->auth[$this->activeAuth];
        }

        return $this->auth[$authName];
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).
     * Returns false when no session initiator is needed.
     *
     * @param string $target
     *            Full URL where external authentication method should
     *            send user to after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiators($target)
    {
        if ($this->getAuth() instanceof PerunShibboleth) {
            return $this->getAuth()->getSessionInitiators($target);
        } else {
            return false;
        }
    }

    /**
     * Determines whether can current User place a reserve on any of passed holdings.
     *
     * We suppose the array of holdings contains items from only one institution.
     *
     * Returns false if are holdings empty or user not logged in.
     *
     * @param unknown $holdings
     * @param unknown $user
     * @return boolean $canReserveWithinInstitution
     */
    public function canReserveWithinInstitution($holdings, $user)
    {
        if (count($holdings) > 0 && $user instanceof \CPK\Db\Row\User) {

            $firstHolding = reset($holdings);

            if (isset($firstHolding['source']))
                $institutionToMatch = $firstHolding['source'];
            else
                $institutionToMatch = reset(explode('.', $firstHolding['id']));

            $userInstitutions = $user->getNonDummyInstitutions();

            foreach ($userInstitutions as $userInstitution) {
                if ($userInstitution === $institutionToMatch)
                    return true;
            }
        }

        return false;
    }

    /**
     * Checks if the activeAuth matches $authToCheckFor, else
     * throws AuthException with desired $errorMessage.
     *
     * @param string $authToCheckFor
     * @param string $errorMessage
     * @throws AuthException
     */
    protected function checkActiveAuthIs($authToCheckFor, $errorMessage = null)
    {
        if ($errorMessage === null)
            $errorMessage = $this->activeAuth . " cannot process desired feature.";

        if ($this->activeAuth !== $authToCheckFor)
            throw new AuthException($errorMessage);
    }

    /**
     * Checks if the activeAuth matches "ShibbolethIdentityManager", else
     * throws AuthException.
     *
     * @throws AuthExeption
     */
    protected function checkActiveAuthIsSIM()
    {
        $this->checkActiveAuthIs("ShibbolethIdentityManager",
            "Account consolidation may provide only ShibbolethIdentityManager authentication method.");
    }
}
