-- We now support the translate extension; but we needed an ability to promote languages over
-- other ones in the interface. Though this is handled in the translate extension itself, we still
-- need to log it.
ALTER TABLE /*$wgDBprefix*/cn_template_log ADD (
  tmplog_begin_prioritylangs text default '',
  tmplog_end_prioritylangs text default ''
);
