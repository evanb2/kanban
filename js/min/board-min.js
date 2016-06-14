function Board(t){$(document).trigger("/board/init/",t),this.record=t,this.$el=$("#board-{0}".sprintf(t.id()));var e=new User(this.record.allowed_users()[this.record.current_user_id()]);this.current_user=function(){return e},this.dom();var s=this;setTimeout(function(){for(var t in s.record.tasks)s.record.tasks[t]=new Task(s.record.tasks[t]);s.project_update_counts(),$(document).trigger("/board/tasks/done/",s.$el)},50)}Board.prototype.dom=function(){var t=this;return $(document).trigger("/board/dom/",t.$el),t.$el.on("click",".col-tasks-sidebar",function(e){if("click"==e.type&&is_dragging)return!1;var s=$(this),r=$(".row-statuses, .row-tasks",t.$el);if(r.is(":animated"))return!1;var o=s.attr("data-left"),a=s.attr("data-right");return s.hasClass("opened")?(s.removeClass("opened"),o=a):s.addClass("opened"),$(".col-tasks-sidebar",t.$el).not(s).removeClass("opened"),r.animate({marginLeft:o},300),!1}),t.$el.on("change",".modal-filter select",function(){var e=$(this),s=e.closest(".modal-filter"),r=$(".btn-filter-reset").show(),o=e.attr("data-field"),a=e.val();return t.record.filters[o]=a,t.apply_filters(),!1}).on("show.bs.modal",".modal-filter",function(){var e=$(this),s=$(".select-projects",e),r=$("option:first",s),o=$("option:last",s);$("option",s).not(r).not(o).remove();for(var a in t.record.project_records){var n=t.record.project_records[a];project_html=templates[t.record.id()]["t-option-project"].render(n),$(project_html).insertAfter(r),t.apply_filters()}}),t.$el.on("click",".btn-status-toggle",function(){var e=$(this),s=e.closest(".col"),r=s.siblings().andSelf(),o=parseInt(e.attr("data-operator")),a=s.index()+o;0>a&&(a=r.length-1),a>=r.length&&(a=0),t.status_cols_toggle(a)}),t.current_user().has_cap("write")?($(".col-tasks",t.$el).sortable({connectWith:".col-tasks",handle:".task-handle",forcePlaceholderSize:!0,forceHelperSize:!0,placeholder:"task-placeholder",containment:$(".row-tasks-wrapper"),appendTo:"body",scroll:!1,helper:"clone",start:function(t,e){$(".col-tasks-sidebar").css({left:"",right:""}),is_dragging=!0},over:function(t,e){},out:function(t,e){},stop:function(t,e){is_dragging=!1},receive:function(e,s){var r=s.item.closest(".col-tasks"),o=s.item.attr("data-id"),a=t.record.tasks[o],n=a.record.status_id,i=t.record.status_records()[n],d=r.attr("data-status-id"),c=t.record.status_records()[d],l=text.task_moved_to_status.sprintf(t.current_user().record().short_name,c.title);l+=text.task_moved_to_status_previous.sprintf(i.title),a.record.status_id=d,a.save(l),t.record.tasks[o].update_status(d),t.updates_status_counts(),t.match_col_h()}}),t.$el.on("mouseenter",".col-tasks",function(){var t=$(this),e=t.attr("data-status-id");return $("#status-"+e).trigger("mouseenter"),!1}).on("mouseleave",".col-tasks",function(){var t=$(this),e=t.attr("data-status-id");return $("#status-"+e).trigger("mouseleave"),!1}).on("shown.bs.dropdown",".col-tasks .dropdown",function(){var t=$(this),e=t.closest(".col-tasks");return e.addClass("active"),!1}).on("hidden.bs.dropdown",".col-tasks .dropdown",function(){var t=$(this),e=t.closest(".col-tasks");return e.removeClass("active"),!1}),t.$el.on("mouseenter",".col-status",function(){var t=$(this),e=t.attr("data-id");return $(".btn-group-status-actions",t).show(),!1}).on("mouseleave",".col-status",function(){var t=$(this),e=t.attr("data-id");return $(".btn-group-status-actions",t).hide(),!1}),t.$el.on("click",".btn-task-new",function(){var e=$(this);$(".glyphicon",e).toggle();var s=e.attr("data-status-id"),r={task:{status_id:s,board_id:t.record.id()},comment:"{0} added the task".sprintf(t.current_user().record().short_name)};return"undefined"!=typeof t.record.settings().default_estimate&&"undefined"!=typeof t.record.estimate_records()[t.record.settings().default_estimate]&&(r.task.estimate_id=t.record.settings().default_estimate),"undefined"!=typeof t.record.settings().default_assigned_to&&"undefined"!=typeof t.record.allowed_users()[t.record.settings().default_assigned_to]&&(r.task.user_id_assigned=t.record.settings().default_assigned_to),"undefined"!=typeof t.record.settings().default_assigned_to_creator&&(t.record.settings().default_assigned_to_creator!==!0&&1!=t.record.settings().default_assigned_to_creator||(r.task.user_id_assigned=t.current_user().record().ID)),r.action="save_task",r.kanban_nonce=$("#kanban_nonce").val(),$.ajax({method:"POST",url:ajaxurl,data:r}).done(function(s){$(".glyphicon",e).toggle();try{if(!s.success)return growl(text.task_added_error),!1;0===Object.keys(t.record.tasks).length&&(t.record.tasks={}),t.record.tasks[s.data.task.id]=new Task(s.data.task),$(".task-title",t.record.tasks[s.data.task.id].$el).focus()}catch(r){}}),!1}),t.$el.on("click",".modal-task-move .list-group-item",function(){var e=$(this),s=e.closest(".modal"),r=$("input.task-id",s),o=r.val(),a=t.record.tasks[o],n=e.attr("data-status-id");a.record.status_id=n,a.save();var i=$("#task-"+o);setTimeout(function(){i.slideUp("fast",function(){a.update_status(n),$(this).prependTo("#status-"+n+"-tasks").slideDown("fast")})},300),t.match_col_h(),t.updates_status_counts()}),$(window).resize(function(){$(".col-tasks",t.$el).sortable("xs"==screen_size?"disable":"enable")}),void $(".col-tasks",t.$el).sortable("xs"==screen_size?"disable":"enable")):!1},Board.prototype.updates_status_counts=function(){$(".col-tasks",this.$el).each(function(){var t=$(this),e=$(".task",t).length,s=t.attr("data-status-id");$("#status-"+s+" .status-task-count").text(e)})},Board.prototype.match_col_h=function(){$(".col-tasks",this.$el).matchHeight({minHeight:window_h})},Board.prototype.apply_filters=function(){var t=this,e=[],s=!1;for(var r in this.record.filters){var o=this.record.filters[r];if(null!==o&&""!==o){s=!0;for(var a in this.record.tasks){var n=this.record.tasks[a];n.record[r]!=o&&e.push("#task-"+a)}$('.modal-filter select[data-field="{0}"]'.sprintf(r),this.$el).val(o)}}s?$(".btn-filter-reset").show():$(".btn-filter-reset").hide();var i=$(e.join(","));i.slideUp("fast"),$(".task").not(i).slideDown("fast"),$(".task",this.$el).promise().done(function(){t.match_col_h()}),url_params=$.extend(url_params,{filters:this.record.filters}),update_url()},Board.prototype.project_update_counts=function(){var t=[];for(var e in this.record.tasks){var s=this.record.tasks[e];"undefined"!=typeof s.record.project_id&&("undefined"==typeof t[s.record.project_id]&&(t[s.record.project_id]=0),t[s.record.project_id]++)}for(var r in this.record.project_records)"undefined"!=typeof t[r]?this.record.project_records[r].task_count=t[r]:this.record.project_records[r].task_count=0},Board.prototype.status_cols_toggle=function(t){url_params.col_index=t,update_url(),$(".row-statuses, .row-tasks",this.$el).each(function(){var e=$(this),s=$("> .col",e),r=s.eq(t).show();s.not(r).hide()})};