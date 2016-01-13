<?php

class ISkype
{

    function QueryInterface(
        &$riid,
        &$ppvObj
    ) {
    }

    function AddRef()
    {
    }

    function Release()
    {
    }

    function GetTypeInfoCount(
        &$pctinfo
    ) {
    }

    function GetTypeInfo(
        $itinfo,
        $lcid,
        &$pptinfo
    ) {
    }

    function GetIDsOfNames(
        &$riid,
        &$rgszNames,
        $cNames,
        $lcid,
        &$rgdispid
    ) {
    }

    function Invoke(
        $dispidMember,
        &$riid,
        $lcid,
        $wFlags,
        &$pdispparams,
        &$pvarResult,
        &$pexcepinfo,
        &$puArgErr
    ) {
    }

    /* Returns/sets Skype API wait timeout in milliseconds. */
    var $Timeout;

    /* Returns/sets USER, CALL, CHAT, CHATMESSAGE or VOICEMAIL object property value. */
    var $Property;

    /* Returns/sets general variable value. */
    var $Variable;

    /* Returns current user handle. */
    var $CurrentUserHandle;

    /* ? [29] */
    /* Returns/sets current user online status. */
    var $CurrentUserStatus;

    /* ? [29] */
    /* Returns connection status. */
    var $ConnectionStatus;

    /* Returns/sets mute status. */
    var $Mute;

    /* Returns Skype application version. */
    var $Version;

    /* Returns current user privilege. */
    var $Privilege;

    /* Returns current user object. */
    var $CurrentUser;

    /* Returns conversion object. */
    var $Convert;

    /* Returns collection of users in the friends list. */
    var $Friends;

    function SearchForUsers(
        $Target
    ) {
        /* Returns collection of users found as the result of search operation. */
    }

    /* Returns collection of calls in the call history. */
    var $Calls;

    /* Returns collection of currently active calls. */
    var $ActiveCalls;

    /* Returns collection of missed calls. */
    var $MissedCalls;

    /* Returns chat messages. */
    var $Messages;

    /* Returns collection of missed messages. */
    var $MissedMessages;

    /* ? [29] */
    /* Returns Skype API attachment status. */
    var $AttachmentStatus;

    /* Returns/sets Skype API protocol version. */
    var $Protocol;

    function Attach(
        $Protocol,
        $Wait
    ) {
        /* Connects to Skype API. */
    }

    function PlaceCall(
        $Target,
        $Target2,
        $Target3,
        $Target4
    ) {
        /* Calls specified target and returns a new call object. */
    }

    function SendMessage(
        $Username,
        $Text
    ) {
        /* Sends IM message to specified user and returns a new message object. */
    }

    /* Returns a new user object. */
    var $User;

    /* Returns a new message object. */
    var $Message;

    /* Returns a new call object. */
    var $Call;

    function SendCommand(
        &$pCommand
    ) {
        /* Sends Skype API command. */
    }

    /* Returns user IM conversations. */
    var $Chats;

    /* Returns new chat object. */
    var $Chat;

    function ChangeUserStatus(
        /* ? [29] [in] */
        $newVal
    ) {
        /* Changes current user online status. */
    }

    /* Returns a new conference object. */
    var $Conference;

    /* Returns collection of conferences. */
    var $Conferences;

    /* Returns user profile property value. */
    var $Profile;

    /* Returns active chats. */
    var $ActiveChats;

    /* Returns missed chats. */
    var $MissedChats;

    /* Returns most recent chats. */
    var $RecentChats;

    /* Returns bookmarked chats. */
    var $BookmarkedChats;

    function CreateChatWith(
        $Username
    ) {
        /* Creates a new chat with a single user. */
    }

    function CreateChatMultiple(
        &$pMembers
    ) {
        /* Creates a new chat with multiple members. */
    }

    /* Retuns voicemails. */
    var $Voicemails;

    function SendVoicemail(
        $Username
    ) {
        /* Sends voicemail to specified user. */
    }

    /* Returns users waiting authorization. */
    var $UsersWaitingAuthorization;

    function ClearChatHistory()
    {
        /* Clears chat history. */
    }

    function ClearVoicemailHistory()
    {
        /* Clears voicemail history. */
    }

    function ClearCallHistory(
        $Username,
        /* ? [29] [in] */
        $Type
    ) {
        /* Clears call history. */
    }

    /* Returns/sets automatic command identifiers. */
    var $CommandId;

    /* Returns new application object. */
    var $Application;

    /* Returns user greeting. */
    var $Greeting;

    /* Enables/disables internal API cache. */
    var $Cache;

    function ResetCache()
    {
        /* Empties command cache. */
    }

    /* Returns logged-in user profile object. */
    var $CurrentUserProfile;

    /* Returns all contact groups. */
    var $Groups;

    /* Returns user defined contact groups. */
    var $CustomGroups;

    /* Returns predefined contact groups. */
    var $HardwiredGroups;

    function CreateGroup(
        $GroupName
    ) {
        /* Creates a new custom group. */
    }

    function DeleteGroup(
        $GroupId
    ) {
        /* Deletes a custom group. */
    }

    /* Returns settings object. */
    var $Settings;

    /* Returns client object. */
    var $Client;

    /* Sets application display name. */
    var $FriendlyName;

    /* Returns a new command object. */
    var $Command;

    /* Returns voicemail object. */
    var $Voicemail;

    /* Returns missed voicemails. */
    var $MissedVoicemails;

    function EnableApiSecurityContext(
        /* ? [29] [in] */
        $Context
    ) {
        /* Enables API security contexts. */
    }

    /* Checks for enabled API security contexts. */
    var $ApiSecurityContextEnabled;

    function CreateSms(
        /* ? [29] [in] */
        $MessageType,
        $TargetNumbers
    ) {
        /* Returns new SMS object. */
    }

    /* Returns SMS messages. */
    var $Smss;

    /* Returns missed SMS messages. */
    var $MissedSmss;

    function SendSms(
        $TargetNumbers,
        $MessageText,
        $ReplyToNumber
    ) {
        /* Sends a SMS messages. */
    }

    function AsyncSearchUsers(
        $Target
    ) {
        /* Search for Skype users. */
    }

    /* Returns API wrapper version. */
    var $ApiWrapperVersion;

    /* Returns/sets silent mode. */
    var $SilentMode;

    /* Returns file transfers. */
    var $FileTransfers;

    /* Returns active file transfers. */
    var $ActiveFileTransfers;

    /* Returns focused contacts. */
    var $FocusedContacts;

    function FindChatUsingBlob(
        $Blob
    ) {
        /* Returns chat mathing the blob. */
    }

    function CreateChatUsingBlob(
        $Blob
    ) {
        /* Returns chat mathing the blob, optionally creating the chat. */
    }

    var $PredictiveDialerCountry;
}
