ALTER TABLE  /*_*/cn_notice_log
CHANGE  notlog_begin_start `notlog_begin_start` binary(14) DEFAULT NULL,
CHANGE  notlog_end_start `notlog_end_start` binary(14) DEFAULT NULL,
CHANGE  notlog_begin_end `notlog_begin_end` binary(14) DEFAULT NULL,
CHANGE  notlog_end_end `notlog_end_end` binary(14) DEFAULT NULL;
