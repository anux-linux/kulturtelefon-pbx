--get the argv values
        local script_name = argv[0];
        local dir_name = argv[1];
        freeswitch.consoleLog("notice", "[streamdir] dir_name: " .. dir_name .. "\n");

--include config.lua
        require "resources.functions.config";

--load libraries
        local Database = require "resources.functions.database";
        local Settings = require "resources.functions.lazy_settings";
        local file     = require "resources.functions.file";
        local log      = require "resources.functions.log".streamdir;

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
                        elseif (obj['digit'] == "9") then
                                return("speed:+1"); -- decrease playback
                        end
                end
        end


--adjust file path
        if not file.exists(dir_name) then
                dir_name = file.exists(sounds_dir.."/"..default_language.."/"..default_dialect.."/"..default_voice.."/"..file_name_only)
                        or file_name
        end
		
--get all sound files in dir
        local i = 0;
        local soundfiles = {};
        local popen = io.popen;
        for filname in popen('find "'..dir_name..'" -type f -print | grep -E "*.wav|*.flac"':lines() do
            freeswitch.consoleLog("notice", "[streamdir] found file: " .. filname .. "\n");
            i = i + 1;
            soundfiles[i] = filename
        end
        freeswitch.consoleLog("notice", "[streamdir] all soundfiles: " .. soundfiles .. "\n");

--stream file if exists, If being called by luarun output filename to stream
        for key, value in ipairs(soundfiles[i]) do
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
        end


