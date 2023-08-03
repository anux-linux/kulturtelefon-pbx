--	streamtimestamp.lua
--	Part of Gottesdienst Telefon
--	Copyright (C) 2023 Antonio Mar <antonio.mark@gottesdienst-telefon.de>
--	All rights reserved.
--
--	Redistribution and use in source and binary forms, with or without
--	modification, are permitted provided that the following conditions are met:
--
--	1. Redistributions of source code must retain the above copyright notice,
--	this list of conditions and the following disclaimer.
--
--	2. Redistributions in binary form must reproduce the above copyright
--	notice, this list of conditions and the following disclaimer in the
--	documentation and/or other materials provided with the distribution.
--
--	THIS SOFTWARE IS PROVIDED ''AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
--	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
--	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
--	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
--	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
--	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
--	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
--	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
--	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
--	POSSIBILITY OF SUCH DAMAGE.

--get the argv values
        local script_name = argv[0];
        local dir_name = argv[1];
        freeswitch.consoleLog("notice", "[streamtimestamp] dir_name: " .. dir_name .. "\n");

--include config.lua
        require "resources.functions.config";

--load libraries
        local Database = require "resources.functions.database";
        local Settings = require "resources.functions.lazy_settings";
        local file     = require "resources.functions.file";
        local log      = require "resources.functions.log".streamtimestamp;

--get the variables
        local domain_name = session:getVariable("domain_name");
        local domain_uuid = session:getVariable("domain_uuid");

--get the sounds dir, language, dialect and voice
        local sounds_dir = session:getVariable("sounds_dir");
        local default_language = session:getVariable("default_language") or 'en';
        local default_dialect = session:getVariable("default_dialect") or 'us';
        local default_voice = session:getVariable("default_voice") or 'callie';

--parse file name
        local dir_name_only = dir_name:match("([^/]+)$");

--settings
        local dbh = Database.new('system');
        local settings = Settings.new(dbh, domain_name, domain_uuid);
        local storage_type = settings:get('recordings', 'storage_type', 'text') or '';

        if (not temp_dir) or (#temp_dir == 0) then
                temp_dir = settings:get('server', 'temp', 'dir') or '/tmp';
        end

        dbh:release()
--define the on_dtmf call back function
        -- luacheck: globals on_dtmf, ignore s arg
        function on_dtmf(s, type, obj, arg)
                if (type == "dtmf") then
                        session:setVariable("dtmf_digits", obj['digit']);
                        log.info("dtmf digit: " .. obj['digit'] .. ", duration: " .. obj['duration']);
                        if (obj['digit'] == "*") then
                                return("false"); --return to previous
                        elseif (obj['digit'] == "0") then
                                return("restart"); --start over
                        elseif (obj['digit'] == "1") then
                                return("volume:-1"); --volume down
                        elseif (obj['digit'] == "3") then
                                return("volume:+1"); -- volume up
                        elseif (obj['digit'] == "4") then
                                return("seek:-5000"); -- back
                        elseif (obj['digit'] == "5") then
                                return("pause"); -- pause toggle
                        elseif (obj['digit'] == "6") then
                                return("seek:+5000"); -- forward
                        elseif (obj['digit'] == "7") then
                                return("speed:-1"); -- increase playback
                        elseif (obj['digit'] == "8") then
                                return("false"); -- stop playback
                        elseif (obj['digit'] == "9") then
                                return("speed:+1"); -- decrease playback
                        end
                end
        end


--adjust file path
        dir_name = sounds_dir.."/music/"..domain_name.."/"..dir_name_only
        freeswitch.consoleLog("notice", "[streamdir] dir_name: "..dir_name.."\n");


--get all sound files in dir

        local i = 0;
        local soundfiles = {};
        local popen = io.popen;
        local date = os.date("%y%m%d")
        freeswitch.consoleLog("notice", 'current date "'..date..'\n')

--stream file if exists, If being called by luarun output filename to stream
        local fiel_name = dir_name.."/"..date..".mp3"
        
        freeswitch.consoleLog("notice", "[streamtimestamp] playback value "..fiel_name.."\n");
        if (session:ready() and stream == nil) then
		session:answer();
		local slept = session:getVariable("slept");
		if (slept == nil or slept == "false") then
			log.notice("sleeping (1s)");
			session:sleep(1000);
			if (slept == "false") then
				session:setVariable("slept", "true");
			end
		end
		session:setInputCallback("on_dtmf", "");
		session:streamFile(file_name);
		session:unsetInputCallback();
	else
		stream:write(file_name);
	end
        




