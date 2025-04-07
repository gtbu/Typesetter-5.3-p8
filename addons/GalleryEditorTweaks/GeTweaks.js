$.extend(gp_editor, {

  editorLoaded : function(){ 
    if( $("#ckeditor_tools a.gallery-editbox-button").length == 0 ){
      gp_editor.editbox_button = 
        $('<a class="ckeditor_control full_width">'
        + '<i class="fa fa-share-square-o fa-flip-horizontal"></i> Edit in Box</a>')
          .on("click", gp_editor.open_box)
          .insertBefore("a.ShowImageSelect");
     }
  },

  open_box : function(){
    if( !gp_editor.thumbs_box ){
      gp_editor.thumbs_box = $('<div class="gpge_box"></div>')
        .appendTo("#ckeditor_area")
      gp_editor.thumbs_box
        .append('<a class="gp_admin_box_close"></a>')
          .find(".gp_admin_box_close")
            .on("click", gp_editor.close_box); 
    }
    gp_editor.thumbs_box
      .append( $("#gp_current_images") )
    gp_editor.thumbs_box
      .draggable({
        cancel : "#gp_current_images"
      })
      .resizable()
      .show();
    gp_editor.editbox_button.hide();
  },
  
  close_box : function(){
    gp_editor.editbox_button.show();
    gp_editor.thumbs_box
      .find("#gp_current_images")
        .insertBefore(gp_editor.editbox_button);
    gp_editor.thumbs_box.hide();
  }

});
