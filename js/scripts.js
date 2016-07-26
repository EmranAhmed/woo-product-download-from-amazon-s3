jQuery(function ($) {


    // Remove an event handler from WooCommerce.
    $(document.body).off('click', '.upload_file_button');

    var downloadable_file_frame;

    $(document.body).on('click', '.upload_file_button', function (event) {

        event.preventDefault();

        var $el = $(this);

        window.file_path_field = $el.closest('tr').find('td.file_url input');
        window.file_name_field = $el.closest('tr').find('td.file_name input');


        // If the media frame already exists, reopen it.
        if (downloadable_file_frame) {
            downloadable_file_frame.open();
            return;
        }

        var downloadable_file_states = [
            // Main states.
            new wp.media.controller.Library({
                library    : wp.media.query(),
                multiple   : true,
                title      : $el.data('choose'),
                priority   : 20,
                filterable : 'uploaded'
            })
        ];

        // Create the media frame.
        downloadable_file_frame = wp.media.frames.downloadable_file = wp.media({
            // Set the title of the modal.
            frame    : 'post',
            title    : $el.data('choose'),
            library  : {
                type : ''
            },
            button   : {
                text : $el.data('update')
            },
            multiple : false,
            state    : 'insert'
            //states: downloadable_file_states,
        });

        downloadable_file_frame.on('menu:render:default', function (view) {
            // Store our views in an object.
            var views = {};

            // Unset default menu items
            view.unset('library-separator');
            view.unset('gallery');
            view.unset('featured-image');
            view.unset('embed');

            // Initialize the views in our view object.
            view.set(views);
        });


        // Create the media frame.
        /*downloadable_file_frame = wp.media.frames.downloadable_file = wp.media( {
         frame: 'post',
         state: 'insert',
         title: $el.data('choose'),
         button: {
         text: $el.data('update')
         },
         multiple: true  // Set to true to allow multiple files to be selected
         } );*/


        // When an image is selected, run a callback.

        //console.log(downloadable_file_frame);

        downloadable_file_frame.on('insert', function () {

            var file_path = '';
            var file_name = '';
            var selection = downloadable_file_frame.state().get('selection');


            //console.log(selection);

            selection.map(function (attachment) {

                attachment = attachment.toJSON();

                // console.log(attachment);

                if (attachment.url) {
                    file_path = attachment.url
                }
                if (attachment.name) {
                    file_name = attachment.name
                }
            });

            window.file_path_field.val(file_path);
            window.file_name_field.val(file_name);
        });

        downloadable_file_frame.on('close', function () {

            var selection = downloadable_file_frame.state().get('selection');

            if (typeof selection == 'undefined') {
                downloadable_file_frame.state().set('selection', {});
            }

            // downloadable_file_frame.state().set('selection', '');
        });

        // Set post to 0 and set our custom type
        /*downloadable_file_frame.on( 'ready', function() {
         downloadable_file_frame.uploader.options.uploader.params = {
         type: 'downloadable_product'
         };
         });*/

        // Finally, open the modal.
        downloadable_file_frame.open();
    });
});