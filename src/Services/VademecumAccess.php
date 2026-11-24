<?php
/**
 * Control de Acceso y Compartición del Vademécum de Semillas y Plantas
 *
 * Reglas:
 * 1. Cualquier usuario de una misma explotación agrícola (id_propietario) puede acceder
 *    al vademécum de cualquier usuario de esa misma explotación.
 * 2. El Superusuario puede compartir el vademécum de un usuario con otro usuario de una
 *    explotación agrícola distinta; en ese momento queda disponible para ese usuario y
 *    para todos los usuarios de esa explotación agrícola destino.
 *
 * Compatible con PHP 5.6, 7.x, 8.x
 */

class VademecumAccess {

    /**
     * Construye la cláusula WHERE (sobre alias `v`) para los registros visibles
     * por el usuario y explotación activos.
     */
    public static function getAccessibleWhereSql($tenantId, $userId, &$params) {
        $tenantId = $tenantId ? (int)$tenantId : 0;
        $userId = (int)$userId;

        $params[':acc_uid'] = $userId;

        $ownParts = array();
        if ($tenantId > 0) {
            $params[':acc_prop'] = $tenantId;
            // Misma explotación agrícola (cualquier usuario de la explotación)
            $ownParts[] = "v.id_propietario = :acc_prop";
            $ownParts[] = "(v.id_propietario IS NULL AND v.id_usuario_creador = :acc_uid)";
        } else {
            $ownParts[] = "v.id_usuario_creador = :acc_uid";
        }

        // Registros compartidos desde otra explotación hacia este usuario o su explotación destino
        $destMatch = "vc.id_usuario_destino = :acc_uid_dest";
        $params[':acc_uid_dest'] = $userId;

        if ($tenantId > 0) {
            $params[':acc_prop_dest'] = $tenantId;
            $destMatch = "(vc.id_usuario_destino = :acc_uid_dest OR vc.id_propietario_destino = :acc_prop_dest)";
        }

        $sharedSubquery = "EXISTS (
            SELECT 1 FROM vademecum_compartido vc
            WHERE vc.activo = 1
              AND {$destMatch}
              AND (
                  v.id_usuario_creador = vc.id_usuario_origen
                  OR (vc.id_propietario_origen IS NOT NULL AND v.id_propietario = vc.id_propietario_origen)
              )
        )";

        return "v.activo = 1 AND ((" . implode(' OR ', $ownParts) . ") OR {$sharedSubquery})";
    }

    /**
     * Comprueba si un registro pertenece a la explotación/usuario actual (editable)
     * frente a un registro compartido desde otra explotación (solo lectura / copiable).
     */
    public static function isOwnOrSameTenant($record, $tenantId, $userId) {
        if (!$record) return false;
        $tenantId = $tenantId ? (int)$tenantId : 0;
        $userId = (int)$userId;

        $recProp = !empty($record['id_propietario']) ? (int)$record['id_propietario'] : 0;
        $recUser = !empty($record['id_usuario_creador']) ? (int)$record['id_usuario_creador'] : 0;

        if ($tenantId > 0 && $recProp === $tenantId) {
            return true;
        }
        if ($recProp === 0 && $recUser === $userId) {
            return true;
        }
        return false;
    }

    /**
     * Obtiene un registro editable (propio o de la misma explotación) por ID.
     */
    public static function findEditableById($db, $idVademecum, $tenantId, $userId) {
        $idVademecum = (int)$idVademecum;
        $tenantId = $tenantId ? (int)$tenantId : 0;
        $userId = (int)$userId;

        if ($tenantId > 0) {
            $stmt = $db->prepare("
                SELECT * FROM vademecum_semillas_plantas
                WHERE id_vademecum = :id AND activo = 1
                  AND (id_propietario = :prop OR (id_propietario IS NULL AND id_usuario_creador = :uid))
                LIMIT 1
            ");
            $stmt->execute(array(':id' => $idVademecum, ':prop' => $tenantId, ':uid' => $userId));
        } else {
            $stmt = $db->prepare("
                SELECT * FROM vademecum_semillas_plantas
                WHERE id_vademecum = :id AND activo = 1 AND id_usuario_creador = :uid
                LIMIT 1
            ");
            $stmt->execute(array(':id' => $idVademecum, ':uid' => $userId));
        }
        return $stmt->fetch();
    }

    /**
     * Obtiene la explotación principal de un usuario (desde usuarios.id_propietario o usuarios_explotaciones).
     */
    public static function getUserPrimaryTenantId($db, $targetUserId) {
        $stmt = $db->prepare("SELECT id_propietario FROM usuarios WHERE id_usuario = :uid LIMIT 1");
        $stmt->execute(array(':uid' => (int)$targetUserId));
        $propId = $stmt->fetchColumn();
        if (!empty($propId)) {
            return (int)$propId;
        }

        $stmt2 = $db->prepare("SELECT id_propietario FROM usuarios_explotaciones WHERE id_usuario = :uid LIMIT 1");
        $stmt2->execute(array(':uid' => (int)$targetUserId));
        $propId2 = $stmt2->fetchColumn();
        return !empty($propId2) ? (int)$propId2 : null;
    }
}
