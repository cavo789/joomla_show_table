<?php

/**
 * Christophe Avonture
 * php version 8.1
 *
 * @package Avonture/joomla_Show_Table
 *
 * @author    Christophe Avonture <christophe@avonture.be>
 * @copyright 2015-2020 (c) Christophe Avonture
 * @license   MIT
 *
 * Written date  : 2016-10-16
 * Last modified : 2024-04-24
 *
 * ! DEFAULT PASSWORD IS "Joomla". See constant PASSWORD below !
 *
 * Changes
 * -------
 * 2017-10-04 - Add export buttons (use https://datatables.net/ and no more table sorter)
 *
 * 2020-05-28 - Update dependencies versions (by Dechèvre Marc - https://www.woluweb.be)
 *      - datatables (js and css):  1.10.21
 *      - datatables buttons (js and css): 1.6.2
 *      - pdfmake: 0.1.62
 *      - jquery: 3.5.1
 *
 * 2020-11-21 - Refactoring
 *
 * 2024-04-24 - PHP 8.2.18 / Joomla 5
 *      - datatables (js and css):  2.0.5
 *      - datatables buttons (js and css): 3.0.2
 *      - pdfmake: 0.0.10
 *      - jquery: 5.3.2
 * 
 * Description
 * -----------
 * This small script will execute a SQL statement against the database
 * of your Joomla website and will show the result in a nice HTML
 * table (bootstrap).
 * When the output is HTML, the DataTables plugin will be used to
 * provide extra functionalities like sorting and filtering.
 *
 * Parameters :
 *
 *   * password : the password define in the PASSWORD constant.
 *
 *   * format   : can be 'HTML' (default) or 'RAW'
 *                RAW will only output a table tag without html headers
 *                or javascript. RAW will be useful when f.i. the table
 *                will be used in a spreadsheet application or as input for
 *                an another program.
 *                For instance : in Excel, you can create a Data Query.
 *                Use the &format=RAW parameter to get a perfect table for Excel.
 *
 *    Add yours : Add your own parameters !
 *    For instance a filter (period=xxxx), a selection (tablename=a_table),
 *    a limit (limit=10), ...
 *
 * Example : https://youriste/show_table.php?password=Joomla&format=RAW
 */

namespace Avonture;

// phpcs:disable PSR1.Files.SideEffects

// This is an example : this SQL will retrieve all users defined in your
// database and will return ID, name, pseudo, email, register date, last visit
// date and the group of the user (registered, super-users, ...)
\define(
    'SQL',
    'SELECT U.id UserID, U.name Name, U.username UserName, ' .
    'U.email eMail, U.registerDate RegisterDate, ' .
    'U.lastvisitDate LastVisitDate, G.title GroupTitle ' .
    'FROM `#__users` U ' .
    'LEFT JOIN (`#__user_usergroup_map` as UG) ON UG.user_id=U.id ' .
    'LEFT JOIN (`#__usergroups` as G) on UG.group_id=G.id ' .
    'ORDER BY registerDate DESC, name, GroupTitle ASC'
);

// SQL statement for retrieving information from, f.i., the content table
/*
define(
    'SQL',
    'SELECT C.id As Article_ID, C.title As Article_Title, '.
    'G.title As Category_Title, '.
    'U.name As Author_Name, C.Hits As Hits, C.language As Language, '.
    'C.created As Writen_Date '.
    'FROM `#__content` C LEFT JOIN `#__categories` G ON C.catid = G.id '.
    'LEFT JOIN `#__users` U on C.created_by=U.id '.
    'WHERE (state=1) '.
    'ORDER BY C.created DESC'
);
*/

/**
 * Run a SQL query against the Joomla database and display the result
 * as a HTML table with or without layout and extra features like
 * filtering, sorting, ...
 */
class ShowTable
{
    /**
     * Title of the page (heading 1)
     *
     * @var string
     */
    public const TITLE = 'Example of Show_Table';

    /**
     * Enable/disable debug mode
     *
     * @var boolean
     */
    public const DEBUG = true;

    /**
     * OS Directory separator
     *
     * @var string
     */
    public const DS = DIRECTORY_SEPARATOR;

    /**
     * Password to use.  The default one is "Joomla"
     *
     * If you want to change, use an online tool like f.i. http://www.md5.cz/
     *
     * @var string
     */
    public const PASSWORD = '57ac91865e5064f231cf620988223590';

    /**
     * Root folder of Joomla. If you've save this script in the root
     * folder of Joomla, just leave __DIR__ otherwise you'll need
     * to update this constant and specify your own root
     *
     * You can manually force a folder like f.i. "C:\Christophe\Sites\beta"
     *
     * @var string
     */
    public const ROOT = __DIR__;

    /**
     * Desired output format
     *
     * @var string f.i. "html" or "raw"
     */
    private $format = '';

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Enable/disable debug mode
        $this->debugMode();

        // Die if the password isn't supplied
        $this->checkPassword();

        // Die if no configuration.php file found
        $this->checkConfiguration();

        // Load Joomla framework
        $this->loadConfiguration();

        // Get the requested format : HTML or RAW.
        // If nothing is specified, HTML will be the default one
        $this->setFormat((string) \filter_input(INPUT_GET, 'format', FILTER_UNSAFE_RAW));
    }

    /**
     * Enable/disable debug mode
     *
     * @return void
     */
    private function debugMode(): void
    {
        if (self::DEBUG) {
            \ini_set('display_errors', '1');
            \ini_set('display_startup_errors', '1');
            \ini_set('html_errors', '1');
            \ini_set('docref_root', 'http://www.php.net/');
            \ini_set(
                'error_prepend_string',
                '<div style=\'color:red; \'font-family:verdana; border:1px solid red; padding:5px;\'>'
            );
            \ini_set('error_append_string', '</div>');
            \error_reporting(E_ALL);
        } else {
            \ini_set('error_reporting', strval(E_ALL & ~E_NOTICE));
        }
    }

    /**
     * Add CSS to the page.
     *
     * @return string
     */
    public function addCSS(): string
    {
        $script = '';

        if ('HTML' === $this->getFormat()) {
            $arr = [
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
                'https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css',
                'https://cdn.datatables.net/2.0.5/css/dataTables.bootstrap5.min.css',
                'https://cdn.datatables.net/buttons/3.0.2/css/buttons.bootstrap5.min.css'
            ];

            foreach ($arr as $style) {
                $script .= "<link rel='stylesheet' href='" . $style . "' " .
                    "rel='stylesheet' media='screen' />\n";
            }
        }

        return $script . "\n";
    }

    /**
     * Add JS to the page.
     *
     * @return string
     */
    public function addJS(): string
    {
        $script = '';

        if ('HTML' === $this->getFormat()) {
            $arr = [
                '//cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js',
                '//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/js/bootstrap.min.js',
                '//cdn.datatables.net/2.0.5/js/dataTables.min.js',
                '//cdn.datatables.net/2.0.5/js/dataTables.bootstrap5.min.js',
                '//cdn.datatables.net/buttons/3.0.2/js/dataTables.buttons.min.js',
                '//cdn.datatables.net/buttons/3.0.2/js/buttons.flash.min.js',
                '//cdn.datatables.net/buttons/3.0.2/js/buttons.print.min.js',
                '//cdn.datatables.net/buttons/3.0.2/js/buttons.html5.min.js',
                '//cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
                '//cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/pdfmake.min.js',
                '//cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.10/vfs_fonts.js',
            ];

            foreach ($arr as $js) {
                $script .= "<script type='text/javascript' src='" . $js . "'></script>\n";
            }

            // Initialize scripts
            $script .= "<script type='text/javascript'>\n" .
                "$(document).ready(function () {\n" .
                "	// Setup - add a text input to each footer cell\n" .
                "	$('#tbl tfoot th').each( function () {\n" .
                "		$(this).html('<input type=\"text\" placeholder=\"Search\" />');\n" .
                "	});\n" .
                "	\n" .
                "	$('#tbl').DataTable({\n" .
                "		'fixedHeader': false,\n" .
                "		'scrollY': '60vh',\n" .
                "		'scrollX': '100%',\n" .
                "		'scrollCollapse': true,\n" .
                "		'info': true,\n" .
                "   	'fixedHeader': true,\n" .
                "   	'dom' : 'Bfrtip',\n" .
                "   	'buttons' : ['copy', 'csv', 'excel', 'print'], \n" .
                "		'lengthMenu': [ \n" .
                "			[25, 50, 100, 500, -1], \n" .
                "			[25, 50, 100, 500, 'All'] \n" .
                "		] \n" .
                "	});\n" .
                "	\n" .
                "	// Apply the search\n" .
                "   var tbl = $('#tbl').DataTable();\n" .
                "	tbl.columns().every(function(){\n" .
                "		var that = this;\n" .
                "		$('input', this.footer()).on('keyup change', function(){\n" .
                "			if (that.search() !== this.value) {\n" .
                "				that.search(this.value).draw();\n" .
                "			}\n" .
                "		});\n" .
                "	});\n" .
                "});\n" .
                '</script>';
        }

        return $script;
    }

    /**
     * Generate the output table
     *
     * @return string The HTML table
     */
    public function outputTable(): string
    {
        $rows = self::getRows();

        $return = '';
        $table = '';

        if (\count($rows) > 0) {
            // Output the table
            $table = '<table id="tbl" class="display compact nowrap order-column">';

            // Output the list of fields name
            $line = '';

            foreach ($rows[0] as $field => $value) {
                $line .= '<th>' . $field . '</th>';
            }

            $table .=
                '<thead><tr>' . $line . '</tr></thead>' .
                '<tfoot><tr>' . $line . '</tr></tfoot>' .
                '<tbody>';

            foreach ($rows as $row) {
                $line = '';

                foreach ($row as $value) {
                    $line .= '<td>' . $value . '</td>';
                }

                $table .= '<tr>' . $line . '</tr>';
            }

            $table .= '</tbody></table>';
        }

        $return = $table;

        if ('HTML' === $this->getFormat()) {
            // Get a few information
            $infos = '<p><strong>Number of records &nbsp;:&nbsp;' .
                \number_format(\count($rows)) . '</strong></p>';

            $sTitle = \trim(self::TITLE);
            if ('' !== $sTitle) {
                $sTitle = '<h1>' . $sTitle . '</h1>';
            }

            $return = '<div style="margin:10px;">' . $sTitle . $table . $infos . '</div>';
        }

        return  $return;
    }

    /**
     * Run the query and return the record set.
     *
     * @return array
     */
    public static function getRows(): array
    {

        $rows = [];

        try {
            $db = \Joomla\CMS\Factory::getDBO();

            $db->setQuery(SQL);

            $rows = $db->loadObjectList();
        } catch (\Exception $exception) {
            echo $exception->getMessage();
        }

        return $rows;
    }

    /**
     * Check if the password is valid; if not, stop immediately.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     *
     * @return void
     */
    private function checkPassword(): void
    {
        // Get the password from the query string
        $password = \filter_input(INPUT_GET, 'password', FILTER_UNSAFE_RAW);

        if (self::PASSWORD !== \md5($password)) {
            header('HTTP/1.0 403 Forbidden');
            echo '<form action="' . $_SERVER['PHP_SELF'] . '" method="GET">' .
                'Password: <input type="text" name="password" />' .
                '<input class="Submit" type="submit" name="submit" /></form>';
            die();
        }
    }

    /**
     * Die if no configuration.php file found.
     *
     * @return void
     */
    private function checkConfiguration(): void
    {
        if (!\file_exists($config = \rtrim(self::ROOT, self::DS) . self::DS . 'configuration.php')) {
            die(
                '<strong>The file ' . $config . ' can\'t be found, please review ' .
                'the ROOT constant to match your website root folder</strong>'
            );
        }
    }

    /**
     * Load Joomla framework.
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     *
     * @return void
     */
    private function loadConfiguration(): void
    {

        /* Initialize Joomla framework */
        define('_JEXEC', 1);
        // Load system defines
        if (file_exists(dirname(__FILE__) . '/defines.php')) {
            include_once dirname(__FILE__) . '/defines.php';
        }

        if (!defined('_JDEFINES')) {
            define('JPATH_BASE', dirname(__FILE__));
            include_once JPATH_BASE . '/includes/defines.php';
        }

        include_once JPATH_BASE . '/includes/framework.php';

        /* Create the Application */
        $container = \Joomla\CMS\Factory::getContainer();

        $container->alias('session.web', 'session.web.site')
            ->alias('session', 'session.web.site')
            ->alias('JSession', 'session.web.site')
            ->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
            ->alias(\Joomla\Session\Session::class, 'session.web.site')
            ->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

        // Instantiate the application.
        $app = $container->get(\Joomla\CMS\Application\SiteApplication::class);

        // Load the extension Namespaces
        \JLoader::register('JNamespacePsr4Map', JPATH_LIBRARIES . '/namespacemap.php');
        $extensionPsr4Loader = new \JNamespacePsr4Map();
        $extensionPsr4Loader->load();


        // Set the application as global app
        \Joomla\CMS\Factory::$application = $app;
    }

    /**
     * Desired output format
     *
     * @param string $format f.i. "HTML" or "RAW"
     *
     * @return void
     */
    public function setFormat(string $format = 'HTML'): void
    {
        if ('' === $format) {
            $format = 'HTML';
        }

        $this->format = \strtoupper($format);

        if (!\in_array($this->format, ['HTML', 'RAW'])) {
            $this->format = 'HTML';
        }
    }

    /**
     * Get the desired output format
     *
     * @return string f.i. "html" or "raw"
     */
    public function getFormat(): string
    {
        return $this->format;
    }
}

$showTable = new ShowTable();

?>

<!DOCTYPE html><html lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta name="robots" content="noindex, nofollow" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=9; IE=8;" />
        <?php echo $showTable->addCSS(); ?>
    </head>
    <style>
        #tbl {margin-left : 0px ;}
    </style>
    <body>
        <?php echo $showTable->outputTable(); ?>
        <?php echo $showTable->addJS(); ?>
    </body>
</html>
