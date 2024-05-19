<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/form/editor.php');

class local_sibguexporttest_atto_editor_form_element extends MoodleQuickForm_editor {

    /** @var array options provided to initalize filepicker */
    protected $_options = array('subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 0, 'changeformat' => 0,
        'areamaxbytes' => FILE_AREA_MAX_BYTES_UNLIMITED, 'context' => null, 'noclean' => 0, 'trusttext' => 0,
        'return_types' => 15, 'enable_filemanagement' => true, 'removeorphaneddrafts' => false, 'autosave' => true,
        'atto:toolbar' => null);
    // 15 is $_options['return_types'] = FILE_INTERNAL | FILE_EXTERNAL | FILE_REFERENCE | FILE_CONTROLLED_LINK.

    /**
     * Returns HTML for editor form element.
     *
     * @return string
     */
    function toHtml() {
        global $CFG, $PAGE, $OUTPUT;
        require_once($CFG->dirroot.'/repository/lib.php');

        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        }

        $ctx = $this->_options['context'];

        $id           = $this->_attributes['id'];
        $elname       = $this->_attributes['name'];

        $subdirs      = $this->_options['subdirs'];
        $maxbytes     = $this->_options['maxbytes'];
        $areamaxbytes = $this->_options['areamaxbytes'];
        $maxfiles     = $this->_options['maxfiles'];
        $changeformat = $this->_options['changeformat']; // TO DO: implement as ajax calls

        $text         = $this->_values['text'];
        $format       = $this->_values['format'];
        $draftitemid  = $this->_values['itemid'];

        // security - never ever allow guest/not logged in user to upload anything
        if (isguestuser() or !isloggedin()) {
            $maxfiles = 0;
        }

        $str = $this->_getTabs();
        $str .= '<div>';

        $editor = get_texteditor('atto');
        $strformats = format_text_menu();
        $formats =  $editor->get_supported_formats();
        foreach ($formats as $fid) {
            $formats[$fid] = $strformats[$fid];
        }

        // get filepicker info
        //
        $fpoptions = array();
        if ($maxfiles != 0 ) {
            if (empty($draftitemid)) {
                // no existing area info provided - let's use fresh new draft area
                require_once("$CFG->libdir/filelib.php");
                $this->setValue(array('itemid'=>file_get_unused_draft_itemid()));
                $draftitemid = $this->_values['itemid'];
            }

            $args = new stdClass();
            // need these three to filter repositories list
            $args->accepted_types = array('web_image');
            $args->return_types = $this->_options['return_types'];
            $args->context = $ctx;
            $args->env = 'filepicker';
            // advimage plugin
            $image_options = initialise_filepicker($args);
            $image_options->context = $ctx;
            $image_options->client_id = uniqid();
            $image_options->maxbytes = $this->_options['maxbytes'];
            $image_options->areamaxbytes = $this->_options['areamaxbytes'];
            $image_options->env = 'editor';
            $image_options->itemid = $draftitemid;

            // moodlemedia plugin
            $args->accepted_types = array('video', 'audio');
            $media_options = initialise_filepicker($args);
            $media_options->context = $ctx;
            $media_options->client_id = uniqid();
            $media_options->maxbytes  = $this->_options['maxbytes'];
            $media_options->areamaxbytes  = $this->_options['areamaxbytes'];
            $media_options->env = 'editor';
            $media_options->itemid = $draftitemid;

            // advlink plugin
            $args->accepted_types = '*';
            $link_options = initialise_filepicker($args);
            $link_options->context = $ctx;
            $link_options->client_id = uniqid();
            $link_options->maxbytes  = $this->_options['maxbytes'];
            $link_options->areamaxbytes  = $this->_options['areamaxbytes'];
            $link_options->env = 'editor';
            $link_options->itemid = $draftitemid;

            $args->accepted_types = array('.vtt');
            $subtitle_options = initialise_filepicker($args);
            $subtitle_options->context = $ctx;
            $subtitle_options->client_id = uniqid();
            $subtitle_options->maxbytes  = $this->_options['maxbytes'];
            $subtitle_options->areamaxbytes  = $this->_options['areamaxbytes'];
            $subtitle_options->env = 'editor';
            $subtitle_options->itemid = $draftitemid;

            if (has_capability('moodle/h5p:deploy', $ctx)) {
                // Only set H5P Plugin settings if the user can deploy new H5P content.
                // H5P plugin.
                $args->accepted_types = array('.h5p');
                $h5poptions = initialise_filepicker($args);
                $h5poptions->context = $ctx;
                $h5poptions->client_id = uniqid();
                $h5poptions->maxbytes  = $this->_options['maxbytes'];
                $h5poptions->areamaxbytes  = $this->_options['areamaxbytes'];
                $h5poptions->env = 'editor';
                $h5poptions->itemid = $draftitemid;
                $fpoptions['h5p'] = $h5poptions;
            }

            $fpoptions['image'] = $image_options;
            $fpoptions['media'] = $media_options;
            $fpoptions['link'] = $link_options;
            $fpoptions['subtitle'] = $subtitle_options;
        }

        // TODO Remove this in MDL-77334 for Moodle 4.6.
        // If editor is required and tinymce, then set required_tinymce option to initalize tinymce validation.
        if (($editor instanceof tinymce_texteditor)  && !is_null($this->getAttribute('onchange'))) {
            $this->_options['required'] = true;
        }

        // print text area - TODO: add on-the-fly switching, size configuration, etc.
        $editor->set_text($text);
        $editor->use_editor($id, $this->_options, $fpoptions);

        $rows = empty($this->_attributes['rows']) ? 15 : $this->_attributes['rows'];
        $cols = empty($this->_attributes['cols']) ? 80 : $this->_attributes['cols'];

        //Apply editor validation if required field
        $context = [];
        $context['rows'] = $rows;
        $context['cols'] = $cols;
        $context['frozen'] = $this->_flagFrozen;
        foreach ($this->getAttributes() as $name => $value) {
            $context[$name] = $value;
        }
        $context['hasformats'] = count($formats) > 1;
        $context['formats'] = [];
        if (($format === '' || $format === null) && count($formats)) {
            $format = key($formats);
        }
        foreach ($formats as $formatvalue => $formattext) {
            $context['formats'][] = ['value' => $formatvalue, 'text' => $formattext, 'selected' => ($formatvalue == $format)];
        }
        $context['id'] = $id;
        $context['value'] = $text;
        $context['format'] = $format;
        $context['formatlabel'] = get_string('editorxformat', 'editor', $this->_label);

        if (!is_null($this->getAttribute('onblur')) && !is_null($this->getAttribute('onchange'))) {
            $context['changelistener'] = true;
        }

        $str .= $OUTPUT->render_from_template('core_form/editor_textarea', $context);

        // during moodle installation, user area doesn't exist
        // so we need to disable filepicker here.
        if (!during_initial_install() && empty($CFG->adminsetuppending)) {
            // 0 means no files, -1 unlimited
            if ($maxfiles != 0 ) {
                $str .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $elname.'[itemid]',
                    'value' => $draftitemid));

                // used by non js editor only
                $editorurl = new moodle_url("$CFG->wwwroot/repository/draftfiles_manager.php", array(
                    'action'=>'browse',
                    'env'=>'editor',
                    'itemid'=>$draftitemid,
                    'subdirs'=>$subdirs,
                    'maxbytes'=>$maxbytes,
                    'areamaxbytes' => $areamaxbytes,
                    'maxfiles'=>$maxfiles,
                    'ctx_id'=>$ctx->id,
                    'course'=>$PAGE->course->id,
                    'sesskey'=>sesskey(),
                ));
                $str .= '<noscript>';
                $str .= "<div><object type='text/html' data='$editorurl' height='160' width='600' style='border:1px solid #000'></object></div>";
                $str .= '</noscript>';
            }
        }


        $str .= '</div>';

        return $str;
    }
}