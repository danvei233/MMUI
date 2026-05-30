import bpy

for obj in bpy.context.scene.objects:
    if obj.type != "MESH":
        continue

    corners = [obj.matrix_world @ __import__("mathutils").Vector(corner) for corner in obj.bound_box]
    mins = [min(corner[i] for corner in corners) for i in range(3)]
    maxs = [max(corner[i] for corner in corners) for i in range(3)]
    print(
        obj.name,
        "loc=", tuple(round(v, 4) for v in obj.location),
        "rot=", tuple(round(v, 4) for v in obj.rotation_euler),
        "scale=", tuple(round(v, 4) for v in obj.scale),
        "min=", tuple(round(v, 4) for v in mins),
        "max=", tuple(round(v, 4) for v in maxs),
        "materials=", [slot.material.name if slot.material else None for slot in obj.material_slots],
    )
